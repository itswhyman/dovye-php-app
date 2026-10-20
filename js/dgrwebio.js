/* KRAL AGARİO DGRWEB — Single-Server Master Engine v11.0 */

let cfg = window.GAME_CONFIG || { speed: 1.4, growthRate: 1.0, maxSplitCells: 16, mapSize: 8000, remergeSec: 10, rgbSpeed: 2 };

let playerNick = 'DGRWEB_Kral';
let playerAvatarImg = null;
let playerSkinPattern = 'rgb_neon';
let socket = null;
let remotePlayers = {};

function handleAvatarUpload(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            playerAvatarImg = new Image();
            playerAvatarImg.src = e.target.result;
            document.getElementById('avatarPreviewBox').textContent = '✅ Fotoğraf Yüklendi ve Hazır!';
        };
        reader.readAsDataURL(file);
    }
}

function startPlatform() {
    const input = document.getElementById('playerNickInput').value.trim();
    if (input.length >= 2) playerNick = input;
    playerSkinPattern = document.getElementById('skinPatternSelect').value;

    document.getElementById('activeUserBadge').textContent = 'Oyuncu: ' + playerNick;
    document.getElementById('nickOverlay').style.display = 'none';

    initEngine();
    initMultiplayerSync();
}

let canvas, ctx, miniCanvas, miniCtx;
let gameRunning = false;

let mouseX = 0, mouseY = 0;
let isSprinting = false;

let cPressCount = 0;
let lastCPressTime = 0;

let playerCells = [];
let foodPellets = [];
let redViruses = [];
let fireballs = [];
let botPlayers = [];
let ejectedFoods = [];

class Cell {
    constructor(id, x, y, radius, color, isPlayer = false, nick = '') {
        this.id = id || Math.random().toString();
        this.x = x;
        this.y = y;
        this.radius = radius;
        this.color = color;
        this.isPlayer = isPlayer;
        this.nick = nick;
        this.vx = 0;
        this.vy = 0;
        this.createdAt = Date.now();
    }

    get score() {
        return Math.floor(this.radius * this.radius / 10);
    }
}

class Food {
    constructor() {
        this.x = Math.random() * (cfg.mapSize - 100) + 50;
        this.y = Math.random() * (cfg.mapSize - 100) + 50;
        const rand = Math.random();
        if (rand < 0.55) {
            this.type = 'blue';
            this.points = 1 * cfg.growthRate;
            this.radius = 5;
            this.color = '#00f2fe';
        } else if (rand < 0.82) {
            this.type = 'yellow';
            this.points = 2 * cfg.growthRate;
            this.radius = 7;
            this.color = '#f59e0b';
        } else {
            this.type = 'green';
            this.points = 4 * cfg.growthRate;
            this.radius = 10;
            this.color = '#00f5a0';
        }
    }
}

class Virus {
    constructor(x, y) {
        this.id = Math.random().toString();
        this.x = x || Math.random() * (cfg.mapSize - 200) + 100;
        this.y = y || Math.random() * (cfg.mapSize - 200) + 100;
        this.radius = 48;
        this.spikes = 16;
    }
}

function respawnPlayer() {
    playerCells = [new Cell('p_' + Math.random(), cfg.mapSize / 2, cfg.mapSize / 2, 22, '#00f2fe', true, playerNick)];
}

function initEngine() {
    canvas = document.getElementById('agarioCanvas');
    ctx = canvas.getContext('2d');
    miniCanvas = document.getElementById('miniCanvas');
    miniCtx = miniCanvas.getContext('2d');

    document.getElementById('radarMapText').textContent = 'RADAR (' + cfg.mapSize + 'px)';

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    respawnPlayer();

    foodPellets = [];
    let foodCount = Math.floor(cfg.mapSize * 0.225);
    for (let i = 0; i < foodCount; i++) foodPellets.push(new Food());

    redViruses = [];
    let virusCount = Math.floor(cfg.mapSize * 0.0056);
    for (let i = 0; i < virusCount; i++) redViruses.push(new Virus());

    botPlayers = [];
    const botNames = ['CyberBot_Alpha', 'NeoMatrix_99', 'SiberWolf', 'TitanGamer', 'ShadowHunter', 'VortexPro', 'QuantumGamer', 'ZeroCool', 'ApexPredator', 'MegaByte'];
    for (let i = 0; i < 30; i++) {
        const name = botNames[i % botNames.length] + '_' + (i + 1);
        const radius = Math.floor(Math.random() * 55) + 18;
        botPlayers.push(new Cell('bot_' + i, Math.random() * cfg.mapSize, Math.random() * cfg.mapSize, radius, '#ff007f', false, name));
    }

    gameRunning = true;

    window.addEventListener('mousemove', (e) => {
        const rect = canvas.getBoundingClientRect();
        mouseX = e.clientX - rect.left;
        mouseY = e.clientY - rect.top;
    });

    window.addEventListener('keydown', handleKeyControls);
    window.addEventListener('keyup', (e) => {
        if (e.key.toLowerCase() === 'b') isSprinting = false;
    });

    requestAnimationFrame(gameLoop);
}

// SINGLE SERVER DUAL MULTIPLAYER SYNC (WebSocket with Auto-Fallback to BroadcastChannel for Same-Domain Friends)
let myClientId = 'client_' + Math.floor(Math.random() * 1000000);
let broadcastChannel = null;

function initMultiplayerSync() {
    // 1. Try WebSocket
    const wsProtocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
    const wsUrl = wsProtocol + window.location.hostname + ':8080';

    try {
        socket = new WebSocket(wsUrl);
        socket.onopen = () => console.log('🌐 Connected to Node.js WebSocket Server!');
        socket.onmessage = (event) => {
            try {
                let data = JSON.parse(event.data);
                if (data.id && data.id !== myClientId) {
                    remotePlayers[data.id] = data;
                }
            } catch (e) {}
        };
    } catch (e) {
        console.log('WebSocket unavailable, using BroadcastChannel for local/domain peer sync.');
    }

    // 2. Fallback / Universal Same-Domain BroadcastChannel
    if ('BroadcastChannel' in window) {
        broadcastChannel = new BroadcastChannel('kral_agario_network');
        broadcastChannel.onmessage = (event) => {
            let data = event.data;
            if (data && data.id && data.id !== myClientId) {
                remotePlayers[data.id] = data;
            }
        };
    }
}

function sendMultiplayerUpdate() {
    if (playerCells.length === 0) return;

    let payload = {
        id: myClientId,
        nick: playerNick,
        cells: playerCells.map(c => ({ x: c.x, y: c.y, radius: c.radius, score: c.score })),
        updatedAt: Date.now()
    };

    if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify(payload));
    }
    if (broadcastChannel) {
        broadcastChannel.postMessage(payload);
    }
}

function handleKeyControls(e) {
    const key = e.key.toLowerCase();

    // SPACE: Split in half IF SCORE >= 30!
    if (e.code === 'Space') {
        if (playerCells.length < cfg.maxSplitCells) {
            let newCells = [];
            playerCells.forEach(cell => {
                if (cell.score >= 30 && playerCells.length + newCells.length < cfg.maxSplitCells) {
                    cell.radius /= 1.414; // Halve mass
                    let angle = Math.atan2(mouseY - canvas.height / 2, mouseX - canvas.width / 2);
                    
                    let splitCell = new Cell('p_' + Math.random(), cell.x, cell.y, cell.radius, cell.color, true, cell.nick);
                    
                    let throwVelocity = (cell.radius * 2.0) / 3.0;
                    splitCell.vx = Math.cos(angle) * throwVelocity;
                    splitCell.vy = Math.sin(angle) * throwVelocity;

                    newCells.push(splitCell);
                }
            });
            playerCells.push(...newCells);
        }
    }

    // W: Eject Food
    if (key === 'w') {
        playerCells.forEach(cell => {
            if (cell.radius > 16) {
                cell.radius = Math.max(12, Math.sqrt(cell.radius * cell.radius - 22));
                let angle = Math.atan2(mouseY - canvas.height / 2, mouseX - canvas.width / 2);
                ejectedFoods.push({
                    x: cell.x + Math.cos(angle) * (cell.radius + 10),
                    y: cell.y + Math.sin(angle) * (cell.radius + 10),
                    vx: Math.cos(angle) * 18,
                    vy: Math.sin(angle) * 18,
                    radius: 7,
                    points: 2,
                    color: '#f59e0b'
                });
            }
        });
    }

    // V: Fireball Attack
    if (key === 'v') {
        if (playerCells[0] && playerCells[0].radius > 20) {
            let angle = Math.atan2(mouseY - canvas.height / 2, mouseX - canvas.width / 2);
            fireballs.push({
                x: playerCells[0].x + Math.cos(angle) * (playerCells[0].radius + 10),
                y: playerCells[0].y + Math.sin(angle) * (playerCells[0].radius + 10),
                vx: Math.cos(angle) * 25,
                vy: Math.sin(angle) * 25,
                radius: 12
            });
        }
    }

    // B: Sprint Hold
    if (key === 'b') {
        isSprinting = true;
    }

    // C: Instant Merge & Corner 'ccc' Cheat
    if (key === 'c') {
        const now = Date.now();
        if (now - lastCPressTime < 800) {
            cPressCount++;
        } else {
            cPressCount = 1;
        }
        lastCPressTime = now;

        let nearCorner = playerCells.some(cell => {
            let margin = 200;
            let left = cell.x < margin;
            let right = cell.x > cfg.mapSize - margin;
            let top = cell.y < margin;
            let bottom = cell.y > cfg.mapSize - margin;
            return (left || right) && (top || bottom);
        });

        if (cPressCount >= 3) {
            if (nearCorner) {
                playerCells.forEach(cell => {
                    cell.radius *= 1.732; // Triples mass
                });
                alert('💥 GİZLİ HİLE AKTİF! Köşede 3x "ccc" basıldı, skorunuz 3 Katına Çıktı!');
            } else {
                if (playerCells.length > 1) {
                    let totalArea = 0;
                    playerCells.forEach(c => totalArea += c.radius * c.radius);
                    playerCells[0].radius = Math.sqrt(totalArea);
                    playerCells = [playerCells[0]];
                }
            }
            cPressCount = 0;
        }
    }
}

function resizeCanvas() {
    if (!canvas) return;
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
}

let lastSprintBurn = 0;
let rgbHue = 0;

function gameLoop(time) {
    if (!gameRunning) return;

    rgbHue = (rgbHue + cfg.rgbSpeed) % 360;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    let totalScore = 0;
    let avgX = 0, avgY = 0;
    playerCells.forEach(c => {
        totalScore += c.score;
        avgX += c.x;
        avgY += c.y;
    });

    // DEATH REDIRECT TO ENTRY SCREEN
    if (playerCells.length === 0) {
        gameRunning = false;
        alert('Eyvah! Yutuldunuz. Yeniden oynamak için giriş ekranına yönlendiriliyorsunuz.');
        document.getElementById('nickOverlay').style.display = 'grid';
        return;
    }

    avgX /= playerCells.length;
    avgY /= playerCells.length;

    // DYNAMIC ZOOM SCALING
    let targetScale = 1.0 / (1.0 + Math.pow(totalScore / 450.0, 0.40));
    let scale = Math.max(0.25, Math.min(1.2, targetScale));

    ctx.save();
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.scale(scale, scale);
    ctx.translate(-avgX, -avgY);

    // Grid Lines
    ctx.strokeStyle = 'rgba(0, 242, 254, 0.08)';
    ctx.lineWidth = 1;
    for (let x = 0; x <= cfg.mapSize; x += 150) {
        ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, cfg.mapSize); ctx.stroke();
    }
    for (let y = 0; y <= cfg.mapSize; y += 150) {
        ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(cfg.mapSize, y); ctx.stroke();
    }

    // Border
    ctx.strokeStyle = '#00f2fe';
    ctx.lineWidth = 8;
    ctx.strokeRect(0, 0, cfg.mapSize, cfg.mapSize);

    // Sprint Logic
    let speedMult = cfg.speed;
    if (isSprinting && time - lastSprintBurn > 500) {
        speedMult = cfg.speed * 2.2;
        playerCells.forEach(c => {
            if (c.radius > 16) c.radius = Math.max(12, Math.sqrt(c.radius * c.radius - 10));
        });
        lastSprintBurn = time;
    }

    // Move Player Cells with Balanced Slowdown Curve
    playerCells.forEach(c => {
        let targetX = avgX + (mouseX - canvas.width / 2) / scale;
        let targetY = avgY + (mouseY - canvas.height / 2) / scale;

        let dx = targetX - c.x;
        let dy = targetY - c.y;
        let dist = Math.hypot(dx, dy);

        // BALANCED MOVEMENT SPEED FORMULA
        let baseSpeed = Math.max(2.2, (85 / Math.sqrt(c.radius))) * speedMult;
        if (dist > 5) {
            c.x += (dx / dist) * baseSpeed + c.vx;
            c.y += (dy / dist) * baseSpeed + c.vy;
        }

        c.vx *= 0.88;
        c.vy *= 0.88;

        c.x = Math.max(c.radius, Math.min(cfg.mapSize - c.radius, c.x));
        c.y = Math.max(c.radius, Math.min(cfg.mapSize - c.radius, c.y));

        // NATURAL MASS DECAY FOR LARGE BLOBS (Prevents infinite giant size)
        if (c.score > 400) {
            c.radius *= 0.99995;
        }
    });

    // SELF-REMERGE AFTER 10 SECONDS
    const now = Date.now();
    for (let i = 0; i < playerCells.length; i++) {
        for (let j = i + 1; j < playerCells.length; j++) {
            let c1 = playerCells[i];
            let c2 = playerCells[j];
            let age1 = (now - c1.createdAt) / 1000;
            let age2 = (now - c2.createdAt) / 1000;

            if (age1 >= cfg.remergeSec || age2 >= cfg.remergeSec) {
                let dist = Math.hypot(c1.x - c2.x, c1.y - c2.y);
                if (dist < c1.radius + c2.radius) {
                    if (c1.radius >= c2.radius) {
                        c1.radius = Math.sqrt(c1.radius * c1.radius + c2.radius * c2.radius);
                        playerCells.splice(j, 1);
                        j--;
                    } else {
                        c2.radius = Math.sqrt(c1.radius * c1.radius + c2.radius * c2.radius);
                        playerCells.splice(i, 1);
                        i--; break;
                    }
                }
            }
        }
    }

    // Draw Food Pellets
    foodPellets.forEach(f => {
        ctx.beginPath();
        ctx.arc(f.x, f.y, f.radius, 0, Math.PI * 2);
        ctx.fillStyle = f.color;
        ctx.fill();
    });

    // Draw Ejected Foods
    ejectedFoods.forEach((ef, idx) => {
        ef.x += ef.vx;
        ef.y += ef.vy;
        ef.vx *= 0.92;
        ef.vy *= 0.92;

        ctx.beginPath();
        ctx.arc(ef.x, ef.y, ef.radius, 0, Math.PI * 2);
        ctx.fillStyle = ef.color;
        ctx.fill();
    });

    // Draw Red Viruses
    redViruses.forEach(v => {
        ctx.save();
        ctx.translate(v.x, v.y);
        ctx.fillStyle = '#ff0055';
        ctx.beginPath();
        for (let i = 0; i < v.spikes; i++) {
            let angle = (i / v.spikes) * Math.PI * 2;
            let r = (i % 2 === 0) ? v.radius : v.radius - 8;
            ctx.lineTo(Math.cos(angle) * r, Math.sin(angle) * r);
        }
        ctx.closePath();
        ctx.fill();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3;
        ctx.stroke();
        ctx.restore();
    });

    // Draw Fireballs
    fireballs.forEach((fb, idx) => {
        fb.x += fb.vx;
        fb.y += fb.vy;

        ctx.beginPath();
        ctx.arc(fb.x, fb.y, fb.radius, 0, Math.PI * 2);
        ctx.fillStyle = '#ff007f';
        ctx.shadowBlur = 18;
        ctx.shadowColor = '#ff007f';
        ctx.fill();
        ctx.shadowBlur = 0;

        botPlayers.forEach((bot) => {
            if (Math.hypot(bot.x - fb.x, bot.y - fb.y) < bot.radius + fb.radius) {
                bot.radius /= 1.414;
                botPlayers.push(new Cell('bot_' + Math.random(), bot.x + 35, bot.y + 35, bot.radius, '#ff007f', false, bot.nick + '_split'));
                fireballs.splice(idx, 1);
            }
        });
    });

    // ACTIVE BOTS AI
    botPlayers.forEach(bot => {
        let nearestFood = null;
        let minDist = 350;
        foodPellets.forEach(f => {
            let d = Math.hypot(f.x - bot.x, f.y - bot.y);
            if (d < minDist) {
                minDist = d;
                nearestFood = f;
            }
        });

        if (nearestFood) {
            let angle = Math.atan2(nearestFood.y - bot.y, nearestFood.x - bot.x);
            bot.x += Math.cos(angle) * 2.8;
            bot.y += Math.sin(angle) * 2.8;
        } else {
            bot.x += (Math.random() - 0.5) * 3.5;
            bot.y += (Math.random() - 0.5) * 3.5;
        }

        bot.x = Math.max(bot.radius, Math.min(cfg.mapSize - bot.radius, bot.x));
        bot.y = Math.max(bot.radius, Math.min(cfg.mapSize - bot.radius, bot.y));

        foodPellets.forEach((f, fIdx) => {
            if (Math.hypot(f.x - bot.x, f.y - bot.y) < bot.radius + f.radius) {
                bot.radius += (f.points * 0.12 * cfg.growthRate); // Balanced growth
                foodPellets[fIdx] = new Food();
            }
        });

        ctx.beginPath();
        ctx.arc(bot.x, bot.y, bot.radius, 0, Math.PI * 2);
        ctx.fillStyle = bot.color;
        ctx.fill();

        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 2;
        ctx.stroke();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold ' + Math.max(12, bot.radius / 2.5) + 'px Plus Jakarta Sans';
        ctx.textAlign = 'center';
        ctx.fillText(bot.nick, bot.x, bot.y + 4);
    });

    // Draw Live Remote Players on same domain
    const nowMs = Date.now();
    Object.keys(remotePlayers).forEach(pId => {
        let remote = remotePlayers[pId];
        if (nowMs - remote.updatedAt < 5000 && remote.cells) {
            remote.cells.forEach(rc => {
                ctx.beginPath();
                ctx.arc(rc.x, rc.y, rc.radius, 0, Math.PI * 2);
                ctx.fillStyle = '#a855f7';
                ctx.fill();
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2.5;
                ctx.stroke();

                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 12px Plus Jakarta Sans';
                ctx.textAlign = 'center';
                ctx.fillText(remote.nick || 'Oyuncu', rc.x, rc.y + 4);
            });
        }
    });

    // Draw Local Player Cells
    playerCells.forEach(c => {
        ctx.save();
        ctx.beginPath();
        ctx.arc(c.x, c.y, c.radius, 0, Math.PI * 2);
        ctx.clip();

        if (playerAvatarImg) {
            ctx.drawImage(playerAvatarImg, c.x - c.radius, c.y - c.radius, c.radius * 2, c.radius * 2);
        } else {
            if (playerSkinPattern.startsWith('rgb')) {
                let grad = ctx.createRadialGradient(c.x, c.y, 5, c.x, c.y, c.radius);
                grad.addColorStop(0, `hsl(${rgbHue}, 100%, 60%)`);
                grad.addColorStop(1, `hsl(${(rgbHue + 80) % 360}, 100%, 40%)`);
                ctx.fillStyle = grad;
            } else if (playerSkinPattern === 'gold_cyber') {
                ctx.fillStyle = '#f59e0b';
            } else {
                ctx.fillStyle = c.color;
            }
            ctx.fill();
        }
        ctx.restore();

        ctx.beginPath();
        ctx.arc(c.x, c.y, c.radius, 0, Math.PI * 2);
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3;
        ctx.stroke();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold ' + Math.max(12, c.radius / 2.5) + 'px Plus Jakarta Sans';
        ctx.textAlign = 'center';
        ctx.fillText(c.nick, c.x, c.y);
        ctx.font = '10px JetBrains Mono';
        ctx.fillText(c.score + ' pts', c.x, c.y + 14);
    });

    // --------------------------------------------------
    // BALANCED GROWTH & STRICT VIRUS BURST MECHANICS
    // --------------------------------------------------

    playerCells.forEach(c => {
        // BALANCED GROWTH FORMULA (Smooth diminishing returns on food)
        foodPellets.forEach((f, idx) => {
            if (Math.hypot(c.x - f.x, c.y - f.y) < c.radius + f.radius) {
                let massGain = f.points * (12.0 / (c.radius + 10.0)) * cfg.growthRate;
                c.radius += massGain;
                foodPellets[idx] = new Food();
            }
        });

        ejectedFoods.forEach((ef, idx) => {
            if (Math.hypot(c.x - ef.x, c.y - ef.y) < c.radius + ef.radius) {
                c.radius += ef.points * 0.12 * cfg.growthRate;
                ejectedFoods.splice(idx, 1);
            }
        });

        // STRICT VIRUS BURST MECHANIC: NO EATING VIRUSES, ALWAYS BURST UPON TOUCH!
        redViruses.forEach((v, vIdx) => {
            if (Math.hypot(c.x - v.x, c.y - v.y) < c.radius) {
                if (c.radius > v.radius) {
                    // Explode into 8 pieces (1 main + 7 smaller burst pieces)
                    let totalMass = c.radius * c.radius;
                    let numSmallPieces = 7;
                    let smallMass = Math.min(200, (totalMass * 0.35) / numSmallPieces);
                    let mainMass = totalMass - (smallMass * numSmallPieces);

                    c.radius = Math.sqrt(mainMass);

                    for (let k = 0; k < numSmallPieces; k++) {
                        if (playerCells.length < cfg.maxSplitCells) {
                            let angle = (k / numSmallPieces) * Math.PI * 2;
                            let smallRadius = Math.sqrt(smallMass);
                            let split = new Cell('p_' + Math.random(), c.x, c.y, smallRadius, c.color, true, c.nick);
                            split.vx = Math.cos(angle) * 20;
                            split.vy = Math.sin(angle) * 20;
                            playerCells.push(split);
                        }
                    }
                    redViruses[vIdx] = new Virus(); // Respawn virus elsewhere
                }
            }
        });
    });

    // CELL VS CELL EATING
    for (let pIdx = playerCells.length - 1; pIdx >= 0; pIdx--) {
        let pc = playerCells[pIdx];
        if (!pc) continue;

        for (let bIdx = botPlayers.length - 1; bIdx >= 0; bIdx--) {
            let bot = botPlayers[bIdx];
            let dist = Math.hypot(pc.x - bot.x, pc.y - bot.y);

            if (pc.radius > bot.radius * 1.1 && dist < pc.radius) {
                pc.radius = Math.sqrt(pc.radius * pc.radius + bot.radius * bot.radius);
                botPlayers.splice(bIdx, 1);
                botPlayers.push(new Cell('bot_' + Math.random(), Math.random() * cfg.mapSize, Math.random() * cfg.mapSize, 20, '#ff007f', false, 'RespawnBot_' + Math.floor(Math.random() * 100)));
            }
            else if (bot.radius > pc.radius * 1.1 && dist < bot.radius) {
                bot.radius = Math.sqrt(bot.radius * bot.radius + pc.radius * pc.radius);
                playerCells.splice(pIdx, 1);
                break;
            }
        }
    }

    ctx.restore();

    renderMinimap(avgX, avgY, scale);
    updateLeaderboard();
    sendMultiplayerUpdate();

    requestAnimationFrame(gameLoop);
}

function renderMinimap(playerX, playerY, currentScale) {
    miniCtx.clearRect(0, 0, 210, 210);
    let miniScale = 210 / cfg.mapSize;

    miniCtx.strokeStyle = 'rgba(0, 242, 254, 0.4)';
    miniCtx.lineWidth = 1;
    miniCtx.strokeRect(0, 0, 210, 210);

    let viewW = (canvas.width / currentScale) * miniScale;
    let viewH = (canvas.height / currentScale) * miniScale;
    let viewX = (playerX * miniScale) - (viewW / 2);
    let viewY = (playerY * miniScale) - (viewH / 2);

    miniCtx.strokeStyle = 'rgba(255, 255, 255, 0.5)';
    miniCtx.strokeRect(viewX, viewY, viewW, viewH);

    miniCtx.fillStyle = '#00f2fe';
    playerCells.forEach(c => {
        let miniR = Math.max(3, Math.min(22, c.radius * miniScale));
        miniCtx.beginPath();
        miniCtx.arc(c.x * miniScale, c.y * miniScale, miniR, 0, Math.PI * 2);
        miniCtx.fill();
    });

    miniCtx.fillStyle = '#ff007f';
    botPlayers.forEach(b => {
        let miniR = Math.max(2, Math.min(18, b.radius * miniScale));
        miniCtx.beginPath();
        miniCtx.arc(b.x * miniScale, b.y * miniScale, miniR, 0, Math.PI * 2);
        miniCtx.fill();
    });
}

function updateLeaderboard() {
    let totalScore = 0;
    playerCells.forEach(c => totalScore += c.score);

    let allScores = [
        { nick: playerNick, score: totalScore },
        ...botPlayers.map(b => ({ nick: b.nick, score: b.score }))
    ];

    allScores.sort((a, b) => b.score - a.score);

    let html = '';
    allScores.slice(0, 10).forEach((item, idx) => {
        let isMe = item.nick === playerNick;
        html += `<div class="leader-item" style="${isMe ? 'color:var(--primary-cyan); font-weight:bold;' : ''}"><span>${idx + 1}. ${item.nick}</span><span>${item.score}</span></div>`;
    });

    document.getElementById('leaderList').innerHTML = html;
}

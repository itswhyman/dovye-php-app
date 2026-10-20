/* KRAL AGARİO DGRWEB — Live Multiplayer Engine v10.0 */

let cfg = window.GAME_CONFIG || { speed: 1.8, maxSplitCells: 16, mapSize: 8000, remergeSec: 10, rgbSpeed: 2 };

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
    initWebSocket();
}

// GAME ENGINE CORE
let canvas, ctx, miniCanvas, miniCtx;
let gameRunning = false;

let mouseX = 0, mouseY = 0;
let isSprinting = false;

// Secret Cheat Counter
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
        this.virusImmunityTimer = 0;
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
            this.points = 1;
            this.radius = 5;
            this.color = '#00f2fe';
        } else if (rand < 0.82) {
            this.type = 'yellow';
            this.points = 2;
            this.radius = 7;
            this.color = '#f59e0b';
        } else {
            this.type = 'green';
            this.points = 4;
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
        this.feedCount = 0;
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

    // Update Radar Label with actual map size
    document.getElementById('radarMapText').textContent = 'RADAR (' + cfg.mapSize + 'px)';

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    respawnPlayer();

    // Food (scaled to map size)
    foodPellets = [];
    let foodCount = Math.floor(cfg.mapSize * 0.225);
    for (let i = 0; i < foodCount; i++) foodPellets.push(new Food());

    // Viruses
    redViruses = [];
    let virusCount = Math.floor(cfg.mapSize * 0.0056);
    for (let i = 0; i < virusCount; i++) redViruses.push(new Virus());

    // Active Bot Competitors with Food-Eating AI
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

// REALTIME WEBSOCKET MULTIPLAYER FOR FRIENDS ON THE SAME DOMAIN
function initWebSocket() {
    const wsProtocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
    const wsUrl = wsProtocol + window.location.host + '/ws';

    try {
        socket = new WebSocket(wsUrl);
        socket.onopen = () => {
            console.log('🌐 Connected to Live Multiplayer Server!');
        };
        socket.onmessage = (event) => {
            try {
                let data = JSON.parse(event.data);
                if (data.type === 'update' && data.id !== socket.id) {
                    remotePlayers[data.id] = data;
                } else if (data.type === 'leave') {
                    delete remotePlayers[data.id];
                }
            } catch (e) {}
        };
    } catch (e) {
        console.log('WebSocket running in standalone/broadcast sync mode.');
    }
}

function sendMultiplayerUpdate() {
    if (socket && socket.readyState === WebSocket.OPEN && playerCells.length > 0) {
        socket.send(JSON.stringify({
            type: 'update',
            nick: playerNick,
            cells: playerCells.map(c => ({ x: c.x, y: c.y, radius: c.radius, score: c.score }))
        }));
    }
}

function handleKeyControls(e) {
    const key = e.key.toLowerCase();

    // SPACE: Split in half & Throw Far IF SCORE >= 30!
    if (e.code === 'Space') {
        if (playerCells.length < cfg.maxSplitCells) {
            let newCells = [];
            playerCells.forEach(cell => {
                // MINIMUM 30 SCORE THRESHOLD REQUIREMENT!
                if (cell.score >= 30 && playerCells.length + newCells.length < cfg.maxSplitCells) {
                    cell.radius /= 1.414; // halve mass
                    let angle = Math.atan2(mouseY - canvas.height / 2, mouseX - canvas.width / 2);
                    
                    let splitCell = new Cell('p_' + Math.random(), cell.x, cell.y, cell.radius, cell.color, true, cell.nick);
                    
                    // Throw distance = 2x radius
                    let throwVelocity = (cell.radius * 2.0) / 3.0;
                    splitCell.vx = Math.cos(angle) * throwVelocity;
                    splitCell.vy = Math.sin(angle) * throwVelocity;

                    newCells.push(splitCell);
                }
            });
            playerCells.push(...newCells);
        }
    }

    // W: Eject Food with Velocity
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

    // SECRET KEY V: Fireball Attack
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

    // SECRET KEY B: Sprint Hold
    if (key === 'b') {
        isSprinting = true;
    }

    // SECRET KEY C & CORNER CHEAT ('ccc' at corners triples score!)
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
                    cell.radius *= 1.732; // Triples mass/area
                });
                alert('💥 GİZLİ HİLE AKTİF! Köşede 3x "ccc" basıldı, skorunuz 3 Katına Çıktı!');
            } else {
                // Instant Merge
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

    // Calculate Total Score & Center of Mass
    let totalScore = 0;
    let avgX = 0, avgY = 0;
    playerCells.forEach(c => {
        totalScore += c.score;
        avgX += c.x;
        avgY += c.y;
    });

    // DEATH REDIRECT TO ENTRY SCREEN REQUIREMENT!
    if (playerCells.length === 0) {
        gameRunning = false;
        alert('Eyvah! Yutuldunuz. Yeniden oynamak için giriş ekranına yönlendiriliyorsunuz.');
        document.getElementById('nickOverlay').style.display = 'grid';
        return;
    }

    avgX /= playerCells.length;
    avgY /= playerCells.length;

    // DYNAMIC SCORE ZOOM SCALING: Perfectly proportional to current score!
    let targetScale = 1.0 / (1.0 + Math.pow(totalScore / 400.0, 0.42));
    let scale = Math.max(0.22, Math.min(1.2, targetScale));

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

    // Move Player Cells
    playerCells.forEach(c => {
        let targetX = avgX + (mouseX - canvas.width / 2) / scale;
        let targetY = avgY + (mouseY - canvas.height / 2) / scale;

        let dx = targetX - c.x;
        let dy = targetY - c.y;
        let dist = Math.hypot(dx, dy);

        let baseSpeed = Math.max(2.5, (100 / c.radius)) * speedMult;
        if (dist > 5) {
            c.x += (dx / dist) * baseSpeed + c.vx;
            c.y += (dy / dist) * baseSpeed + c.vy;
        }

        c.vx *= 0.88;
        c.vy *= 0.88;

        c.x = Math.max(c.radius, Math.min(cfg.mapSize - c.radius, c.x));
        c.y = Math.max(c.radius, Math.min(cfg.mapSize - c.radius, c.y));

        if (c.virusImmunityTimer > 0) c.virusImmunityTimer--;
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

    // Move & Draw Ejected Foods
    ejectedFoods.forEach((ef, idx) => {
        ef.x += ef.vx;
        ef.y += ef.vy;
        ef.vx *= 0.92;
        ef.vy *= 0.92;

        ctx.beginPath();
        ctx.arc(ef.x, ef.y, ef.radius, 0, Math.PI * 2);
        ctx.fillStyle = ef.color;
        ctx.fill();

        redViruses.forEach((v) => {
            if (Math.hypot(v.x - ef.x, v.y - ef.y) < v.radius) {
                v.feedCount++;
                v.radius += 1.5;
                ejectedFoods.splice(idx, 1);

                if (v.feedCount >= 6) {
                    redViruses.push(new Virus(v.x + 80, v.y + 80));
                    v.feedCount = 0;
                    v.radius = 48;
                }
            }
        });
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

    // Fireballs
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

    // ACTIVE BOTS AI: WANDERING & FOOD EATING REQUIREMENT!
    botPlayers.forEach(bot => {
        // Find nearest food pellet
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
            bot.x += Math.cos(angle) * 3.2;
            bot.y += Math.sin(angle) * 3.2;
        } else {
            bot.x += (Math.random() - 0.5) * 4;
            bot.y += (Math.random() - 0.5) * 4;
        }

        bot.x = Math.max(bot.radius, Math.min(cfg.mapSize - bot.radius, bot.x));
        bot.y = Math.max(bot.radius, Math.min(cfg.mapSize - bot.radius, bot.y));

        // Bot eats food!
        foodPellets.forEach((f, fIdx) => {
            if (Math.hypot(f.x - bot.x, f.y - bot.y) < bot.radius + f.radius) {
                bot.radius += f.points * 0.16;
                foodPellets[fIdx] = new Food(); // Respawn food
            }
        });

        // Draw Bot
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

    // Draw Player Cells
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
        ctx.strokeStyle = c.virusImmunityTimer > 0 ? '#00f5a0' : '#ffffff';
        ctx.lineWidth = c.virusImmunityTimer > 0 ? 5 : 3;
        ctx.stroke();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold ' + Math.max(12, c.radius / 2.5) + 'px Plus Jakarta Sans';
        ctx.textAlign = 'center';
        ctx.fillText(c.nick, c.x, c.y);
        ctx.font = '10px JetBrains Mono';
        ctx.fillText(c.score + ' pts', c.x, c.y + 14);
    });

    // --------------------------------------------------
    // COLLISIONS & VIRUS BURST MECHANIC REQUIREMENT!
    // --------------------------------------------------

    // 1. Player Eats Food
    playerCells.forEach(c => {
        foodPellets.forEach((f, idx) => {
            if (Math.hypot(c.x - f.x, c.y - f.y) < c.radius + f.radius) {
                c.radius += f.points * 0.16;
                foodPellets[idx] = new Food();
            }
        });

        ejectedFoods.forEach((ef, idx) => {
            if (Math.hypot(c.x - ef.x, c.y - ef.y) < c.radius + ef.radius) {
                c.radius += ef.points * 0.16;
                ejectedFoods.splice(idx, 1);
            }
        });

        // 2. VIRUS BURST REQUIREMENT:
        // Do NOT eat immediately! Must split into 1 slightly larger piece + 7 smaller burst pieces!
        redViruses.forEach((v, vIdx) => {
            if (Math.hypot(c.x - v.x, c.y - v.y) < c.radius) {
                if (c.virusImmunityTimer > 0 && c.radius > v.radius) {
                    c.radius += 14;
                    redViruses[vIdx] = new Virus();
                } else if (c.radius > v.radius) {
                    // Explode into 8 pieces total (1 main + 7 small burst pieces)
                    let totalMass = c.radius * c.radius;
                    let numSmallPieces = 7;
                    let smallMass = Math.min(220, (totalMass * 0.35) / numSmallPieces); // Smaller than virus score
                    let mainMass = totalMass - (smallMass * numSmallPieces);

                    c.radius = Math.sqrt(mainMass);
                    c.virusImmunityTimer = 1200; // 20s immunity

                    for (let k = 0; k < numSmallPieces; k++) {
                        if (playerCells.length < cfg.maxSplitCells) {
                            let angle = (k / numSmallPieces) * Math.PI * 2;
                            let smallRadius = Math.sqrt(smallMass);
                            let split = new Cell('p_' + Math.random(), c.x, c.y, smallRadius, c.color, true, c.nick);
                            split.vx = Math.cos(angle) * 20;
                            split.vy = Math.sin(angle) * 20;
                            split.virusImmunityTimer = 1200;
                            playerCells.push(split);
                        }
                    }
                    redViruses[vIdx] = new Virus(); // Respawn virus
                }
            }
        });
    });

    // 3. CELL VS CELL EATING (PIECE-BASED STEALING)
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

// ACCURATE MINIMAP ENGINE WITH PERFECT VIEWPORT BOX & SPOT SYNC REQUIREMENT!
function renderMinimap(playerX, playerY, currentScale) {
    miniCtx.clearRect(0, 0, 210, 210);
    let miniScale = 210 / cfg.mapSize;

    // Draw Map Border Box on Radar
    miniCtx.strokeStyle = 'rgba(0, 242, 254, 0.4)';
    miniCtx.lineWidth = 1;
    miniCtx.strokeRect(0, 0, 210, 210);

    // Draw Camera Viewport Area Box on Radar
    let viewW = (canvas.width / currentScale) * miniScale;
    let viewH = (canvas.height / currentScale) * miniScale;
    let viewX = (playerX * miniScale) - (viewW / 2);
    let viewY = (playerY * miniScale) - (viewH / 2);

    miniCtx.strokeStyle = 'rgba(255, 255, 255, 0.5)';
    miniCtx.strokeRect(viewX, viewY, viewW, viewH);

    // Draw Player Dots scaled to size
    miniCtx.fillStyle = '#00f2fe';
    playerCells.forEach(c => {
        let miniR = Math.max(3, Math.min(22, c.radius * miniScale));
        miniCtx.beginPath();
        miniCtx.arc(c.x * miniScale, c.y * miniScale, miniR, 0, Math.PI * 2);
        miniCtx.fill();
    });

    // Draw Bots Dots scaled to size
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

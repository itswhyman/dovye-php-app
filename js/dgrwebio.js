/* TurkBil Oyun Fuarı — DGRWEB.IO Enterprise Master Engine v8.0 */

let playerNick = 'DGRWEB_Player';
let playerAvatarImg = null;
let playerSkinPattern = 'rgb';

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
}

// GAME ENGINE CORE
let canvas, ctx, miniCanvas, miniCtx;
let gameRunning = false;
const MAP_SIZE = 8000;

let mouseX = 0, mouseY = 0;
let isSprinting = false;

// Cheat Tracker
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
        this.remergeTimer = 0;
        this.virusImmunityTimer = 0; // 20 sec virus eating buff
    }

    get score() {
        return Math.floor(this.radius * this.radius / 10);
    }
}

class Food {
    constructor() {
        this.x = Math.random() * (MAP_SIZE - 100) + 50;
        this.y = Math.random() * (MAP_SIZE - 100) + 50;
        const rand = Math.random();
        if (rand < 0.55) {
            this.type = 'blue'; // 1 pt
            this.points = 1;
            this.radius = 5;
            this.color = '#00f2fe';
        } else if (rand < 0.82) {
            this.type = 'yellow'; // 2 pt
            this.points = 2;
            this.radius = 7;
            this.color = '#f59e0b';
        } else {
            this.type = 'green'; // 4 pt
            this.points = 4;
            this.radius = 10;
            this.color = '#00f5a0';
        }
    }
}

class Virus {
    constructor(x, y) {
        this.id = Math.random().toString();
        this.x = x || Math.random() * (MAP_SIZE - 200) + 100;
        this.y = y || Math.random() * (MAP_SIZE - 200) + 100;
        this.radius = 45;
        this.spikes = 16;
        this.feedCount = 0;
    }
}

function launchGame(gameKey) {
    if (gameKey !== 'dgrwebio') return;

    document.getElementById('menuContainer').style.display = 'none';
    document.getElementById('arenaContainer').style.display = 'block';

    canvas = document.getElementById('agarioCanvas');
    ctx = canvas.getContext('2d');
    miniCanvas = document.getElementById('miniCanvas');
    miniCtx = miniCanvas.getContext('2d');

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // Init Player
    playerCells = [new Cell('player_1', MAP_SIZE / 2, MAP_SIZE / 2, 22, '#00f2fe', true, playerNick)];

    // Init Food (1800 pellets in 8000px map)
    foodPellets = [];
    for (let i = 0; i < 1800; i++) foodPellets.push(new Food());

    // Init Red Viruses (45 items)
    redViruses = [];
    for (let i = 0; i < 45; i++) redViruses.push(new Virus());

    // Init Active Bot Competitors (30 Bots with multi-cells)
    botPlayers = [];
    const botNames = ['CyberBot_Alpha', 'NeoMatrix_99', 'SiberWolf', 'TitanGamer', 'ShadowHunter', 'VortexPro', 'QuantumGamer', 'ZeroCool'];
    for (let i = 0; i < 30; i++) {
        const name = botNames[i % botNames.length] + '_' + (i + 1);
        const radius = Math.floor(Math.random() * 50) + 18;
        botPlayers.push(new Cell('bot_' + i, Math.random() * MAP_SIZE, Math.random() * MAP_SIZE, radius, '#ff007f', false, name));
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

function handleKeyControls(e) {
    const key = e.key.toLowerCase();

    // SPACE: Split in half
    if (e.code === 'Space') {
        let newCells = [];
        playerCells.forEach(cell => {
            if (cell.radius >= 22) {
                cell.radius /= 1.414;
                let angle = Math.atan2(mouseY - canvas.height / 2, mouseX - canvas.width / 2);
                let splitCell = new Cell('p_' + Math.random(), cell.x, cell.y, cell.radius, cell.color, true, cell.nick);
                splitCell.vx = Math.cos(angle) * 22;
                splitCell.vy = Math.sin(angle) * 22;
                splitCell.remergeTimer = 350;
                newCells.push(splitCell);
            }
        });
        playerCells.push(...newCells);
    }

    // W: Eject Food with Velocity towards Cursor!
    if (key === 'w') {
        playerCells.forEach(cell => {
            if (cell.radius > 16) {
                cell.radius = Math.max(12, Math.sqrt(cell.radius * cell.radius - 22));
                let angle = Math.atan2(mouseY - canvas.height / 2, mouseX - canvas.width / 2);
                ejectedFoods.push({
                    x: cell.x + Math.cos(angle) * (cell.radius + 10),
                    y: cell.y + Math.sin(angle) * (cell.radius + 10),
                    vx: Math.cos(angle) * 16,
                    vy: Math.sin(angle) * 16,
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
                vx: Math.cos(angle) * 24,
                vy: Math.sin(angle) * 24,
                radius: 12
            });
        }
    }

    // SECRET KEY B: Sprint Hold (Burns 10 pt per sec)
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

        // Check if player is near map corners
        let nearCorner = playerCells.some(cell => {
            let margin = 200;
            let left = cell.x < margin;
            let right = cell.x > MAP_SIZE - margin;
            let top = cell.y < margin;
            let bottom = cell.y > MAP_SIZE - margin;
            return (left || right) && (top || bottom);
        });

        if (cPressCount >= 3) {
            if (nearCorner) {
                playerCells.forEach(cell => {
                    cell.radius *= 1.732; // Triples mass/area (score x3)
                });
                alert('💥 GİZLİ HİLE AKTİF! Köşede 3x "ccc" basıldı, skorunuz 3 Katına Çıktı!');
            } else {
                // Regular Instant Merge
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
    canvas.width = canvas.parentElement.clientWidth;
    canvas.height = canvas.parentElement.clientHeight;
}

let lastSprintBurn = 0;
let rgbHue = 0;

function gameLoop(time) {
    if (!gameRunning) return;

    rgbHue = (rgbHue + 1) % 360;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Camera Center of Mass
    let avgX = 0, avgY = 0, totalR = 0;
    playerCells.forEach(c => {
        avgX += c.x;
        avgY += c.y;
        totalR += c.radius;
    });
    if (playerCells.length === 0) {
        // Respawn if eaten
        playerCells = [new Cell('player_respawn', MAP_SIZE / 2, MAP_SIZE / 2, 22, '#00f2fe', true, playerNick)];
        avgX = MAP_SIZE / 2; avgY = MAP_SIZE / 2; totalR = 22;
    } else {
        avgX /= playerCells.length;
        avgY /= playerCells.length;
    }

    let scale = Math.max(0.3, Math.min(1.2, 35 / (totalR + 10)));
    
    ctx.save();
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.scale(scale, scale);
    ctx.translate(-avgX, -avgY);

    // Draw Grid Lines & Coordinates
    ctx.strokeStyle = 'rgba(0, 242, 254, 0.08)';
    ctx.lineWidth = 1;
    for (let x = 0; x <= MAP_SIZE; x += 150) {
        ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, MAP_SIZE); ctx.stroke();
    }
    for (let y = 0; y <= MAP_SIZE; y += 150) {
        ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(MAP_SIZE, y); ctx.stroke();
    }

    // Border
    ctx.strokeStyle = '#00f2fe';
    ctx.lineWidth = 8;
    ctx.strokeRect(0, 0, MAP_SIZE, MAP_SIZE);

    // Sprint Logic (B key burns 10 mass per second)
    let speedMult = 1.0;
    if (isSprinting && time - lastSprintBurn > 500) {
        speedMult = 2.2;
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

        let baseSpeed = Math.max(2.5, (90 / c.radius)) * speedMult;
        if (dist > 5) {
            c.x += (dx / dist) * baseSpeed + c.vx;
            c.y += (dy / dist) * baseSpeed + c.vy;
        }

        c.vx *= 0.92;
        c.vy *= 0.92;

        c.x = Math.max(c.radius, Math.min(MAP_SIZE - c.radius, c.x));
        c.y = Math.max(c.radius, Math.min(MAP_SIZE - c.radius, c.y));

        if (c.remergeTimer > 0) c.remergeTimer--;
        if (c.virusImmunityTimer > 0) c.virusImmunityTimer--;
    });

    // Draw Static Food Pellets
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

        // Check Collision with Viruses (Virus Feeding)
        redViruses.forEach((v, vIdx) => {
            if (Math.hypot(v.x - ef.x, v.y - ef.y) < v.radius) {
                v.feedCount++;
                v.radius += 1.5;
                ejectedFoods.splice(idx, 1);

                // If fed enough, virus splits/spawns a new virus!
                if (v.feedCount >= 6) {
                    redViruses.push(new Virus(v.x + 80, v.y + 80));
                    v.feedCount = 0;
                    v.radius = 45;
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

    // Move & Draw Fireballs
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

        // Hit Bot -> Splits Bot!
        botPlayers.forEach((bot, bIdx) => {
            if (Math.hypot(bot.x - fb.x, bot.y - fb.y) < bot.radius + fb.radius) {
                bot.radius /= 1.414;
                botPlayers.push(new Cell('bot_' + Math.random(), bot.x + 35, bot.y + 35, bot.radius, '#ff007f', false, bot.nick + '_split'));
                fireballs.splice(idx, 1);
            }
        });
    });

    // Move & Draw Bots
    botPlayers.forEach(bot => {
        bot.x += (Math.random() - 0.5) * 4;
        bot.y += (Math.random() - 0.5) * 4;
        bot.x = Math.max(bot.radius, Math.min(MAP_SIZE - bot.radius, bot.x));
        bot.y = Math.max(bot.radius, Math.min(MAP_SIZE - bot.radius, bot.y));

        ctx.beginPath();
        ctx.arc(bot.x, bot.y, bot.radius, 0, Math.PI * 2);
        ctx.fillStyle = bot.color;
        ctx.fill();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 12px Plus Jakarta Sans';
        ctx.textAlign = 'center';
        ctx.fillText(bot.nick, bot.x, bot.y + 4);
    });

    // Draw Player Cells (RGB Gradient / Skin / Custom Photo)
    playerCells.forEach(c => {
        ctx.save();
        ctx.beginPath();
        ctx.arc(c.x, c.y, c.radius, 0, Math.PI * 2);
        ctx.clip();

        if (playerAvatarImg) {
            ctx.drawImage(playerAvatarImg, c.x - c.radius, c.y - c.radius, c.radius * 2, c.radius * 2);
        } else {
            if (playerSkinPattern === 'rgb') {
                let grad = ctx.createRadialGradient(c.x, c.y, 5, c.x, c.y, c.radius);
                grad.addColorStop(0, `hsl(${rgbHue}, 100%, 60%)`);
                grad.addColorStop(1, `hsl(${(rgbHue + 60) % 360}, 100%, 40%)`);
                ctx.fillStyle = grad;
            } else if (playerSkinPattern === 'gold') {
                ctx.fillStyle = '#f59e0b';
            } else {
                ctx.fillStyle = c.color;
            }
            ctx.fill();
        }
        ctx.restore();

        // Stroke Border (Glows if Virus Immunity Buff is Active)
        ctx.beginPath();
        ctx.arc(c.x, c.y, c.radius, 0, Math.PI * 2);
        ctx.strokeStyle = c.virusImmunityTimer > 0 ? '#00f5a0' : '#ffffff';
        ctx.lineWidth = c.virusImmunityTimer > 0 ? 5 : 3;
        ctx.stroke();

        // Nick Label & Score
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold ' + Math.max(12, c.radius / 2.5) + 'px Plus Jakarta Sans';
        ctx.textAlign = 'center';
        ctx.fillText(c.nick, c.x, c.y);
        ctx.font = '10px JetBrains Mono';
        ctx.fillText(c.score + ' pts', c.x, c.y + 14);
    });

    // --------------------------------------------------
    // COLLISION & EATING ALGORITHMS
    // --------------------------------------------------

    // 1. Player eats Food Pellets & Ejected Foods
    playerCells.forEach(c => {
        foodPellets.forEach((f, idx) => {
            if (Math.hypot(c.x - f.x, c.y - f.y) < c.radius + f.radius) {
                c.radius += f.points * 0.16;
                foodPellets[idx] = new Food(); // Respawn
            }
        });

        ejectedFoods.forEach((ef, idx) => {
            if (Math.hypot(c.x - ef.x, c.y - ef.y) < c.radius + ef.radius) {
                c.radius += ef.points * 0.16;
                ejectedFoods.splice(idx, 1);
            }
        });

        // 2. Player vs Virus (Pop or 20s Immunity Eating!)
        redViruses.forEach((v, vIdx) => {
            if (Math.hypot(c.x - v.x, c.y - v.y) < c.radius) {
                if (c.virusImmunityTimer > 0 && c.radius > v.radius) {
                    // EAT VIRUS FOR POINTS (20s Buff Active!)
                    c.radius += 12;
                    redViruses[vIdx] = new Virus(); // Respawn
                } else if (c.radius > v.radius) {
                    // Pop into pieces & Grant 20s Immunity Buff!
                    c.radius /= 1.5;
                    c.virusImmunityTimer = 1200; // 20s at 60fps
                    for (let k = 0; k < 3; k++) {
                        let split = new Cell('p_' + Math.random(), c.x + (Math.random() - 0.5) * 60, c.y + (Math.random() - 0.5) * 60, c.radius, c.color, true, c.nick);
                        split.remergeTimer = 400;
                        split.virusImmunityTimer = 1200;
                        playerCells.push(split);
                    }
                }
            }
        });
    });

    // 3. CELL VS CELL EATING (Player vs Bot, Cell vs Cell Stealing!)
    playerCells.forEach((pc, pIdx) => {
        botPlayers.forEach((bot, bIdx) => {
            let dist = Math.hypot(pc.x - bot.x, pc.y - bot.y);

            // Player eats Bot cell!
            if (pc.radius > bot.radius * 1.1 && dist < pc.radius) {
                pc.radius = Math.sqrt(pc.radius * pc.radius + bot.radius * bot.radius);
                botPlayers.splice(bIdx, 1);
                // Respawn new bot
                botPlayers.push(new Cell('bot_' + Math.random(), Math.random() * MAP_SIZE, Math.random() * MAP_SIZE, 20, '#ff007f', false, 'RespawnBot_' + Math.floor(Math.random() * 100)));
            }

            // Bot eats Player cell!
            if (bot.radius > pc.radius * 1.1 && dist < bot.radius) {
                bot.radius = Math.sqrt(bot.radius * bot.radius + pc.radius * pc.radius);
                playerCells.splice(pIdx, 1);
            }
        });
    });

    ctx.restore();

    renderMinimap(avgX, avgY);
    updateLeaderboard();

    requestAnimationFrame(gameLoop);
}

function renderMinimap(playerX, playerY) {
    miniCtx.clearRect(0, 0, 200, 200);
    let scale = 200 / MAP_SIZE;

    // Player Dot
    miniCtx.fillStyle = '#00f2fe';
    miniCtx.beginPath();
    miniCtx.arc(playerX * scale, playerY * scale, 5, 0, Math.PI * 2);
    miniCtx.fill();

    // Bots Dots
    miniCtx.fillStyle = '#ff007f';
    botPlayers.forEach(b => {
        miniCtx.beginPath();
        miniCtx.arc(b.x * scale, b.y * scale, 3, 0, Math.PI * 2);
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

// --------------------------------------------------
// MINI-GAMES EMBEDDED ENGINE (Snake, Flappy, Pong, Brick Breaker)
// --------------------------------------------------

function openMiniGameModal(type) {
    const modal = document.getElementById('miniGameModal');
    const title = document.getElementById('miniGameTitle');
    const canvas = document.getElementById('miniGameCanvas');
    const scoreText = document.getElementById('miniGameScoreText');

    modal.style.display = 'grid';

    if (type === 'snake') {
        title.textContent = 'Cyber Snake 2077';
        startSnakeGame(canvas, scoreText);
    } else if (type === 'flappy') {
        title.textContent = 'Flappy Blob Arcadius';
        startFlappyGame(canvas, scoreText);
    } else if (type === 'pong') {
        title.textContent = 'Neon Pong vs AI';
        startPongGame(canvas, scoreText);
    } else if (type === 'breaker') {
        title.textContent = 'Cyber Brick Breaker';
        startBreakerGame(canvas, scoreText);
    }
}

function closeMiniGameModal() {
    document.getElementById('miniGameModal').style.display = 'none';
}

// Snake Mini-Game
function startSnakeGame(canvas, scoreText) {
    const ctx = canvas.getContext('2d');
    let snake = [{x: 10, y: 10}];
    let food = {x: 15, y: 15};
    let dx = 1, dy = 0;
    let score = 0;

    function gameStep() {
        let head = {x: snake[0].x + dx, y: snake[0].y + dy};
        snake.unshift(head);

        if (head.x === food.x && head.y === food.y) {
            score += 10;
            scoreText.textContent = 'Skor: ' + score;
            food = {x: Math.floor(Math.random() * 28), y: Math.floor(Math.random() * 18)};
        } else {
            snake.pop();
        }

        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.fillStyle = '#00f2fe';
        snake.forEach(s => ctx.fillRect(s.x * 20, s.y * 20, 18, 18));

        ctx.fillStyle = '#ff007f';
        ctx.fillRect(food.x * 20, food.y * 20, 18, 18);
    }

    window.onkeydown = (e) => {
        if (e.key === 'ArrowUp' && dy === 0) { dx = 0; dy = -1; }
        if (e.key === 'ArrowDown' && dy === 0) { dx = 0; dy = 1; }
        if (e.key === 'ArrowLeft' && dx === 0) { dx = -1; dy = 0; }
        if (e.key === 'ArrowRight' && dx === 0) { dx = 1; dy = 0; }
    };

    let timer = setInterval(() => {
        if (document.getElementById('miniGameModal').style.display === 'none') {
            clearInterval(timer); return;
        }
        gameStep();
    }, 100);
}

// Flappy Mini-Game
function startFlappyGame(canvas, scoreText) {
    const ctx = canvas.getContext('2d');
    let birdY = 200, velocity = 0, score = 0;
    let pipes = [];

    canvas.onclick = () => { velocity = -7; };

    let timer = setInterval(() => {
        if (document.getElementById('miniGameModal').style.display === 'none') {
            clearInterval(timer); return;
        }

        velocity += 0.4;
        birdY += velocity;

        if (Math.random() < 0.03) {
            pipes.push({x: 600, top: Math.random() * 200 + 50});
        }

        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.fillStyle = '#00f2fe';
        ctx.beginPath(); ctx.arc(100, birdY, 12, 0, Math.PI * 2); ctx.fill();

        pipes.forEach((p, idx) => {
            p.x -= 3;
            ctx.fillStyle = '#00f5a0';
            ctx.fillRect(p.x, 0, 30, p.top);
            ctx.fillRect(p.x, p.top + 100, 30, 400);

            if (p.x === 100) { score += 10; scoreText.textContent = 'Skor: ' + score; }
        });
    }, 20);
}

// Pong Mini-Game
function startPongGame(canvas, scoreText) {
    const ctx = canvas.getContext('2d');
    let ballX = 300, ballY = 200, bx = 4, by = 4;
    let p1Y = 150, p2Y = 150, score = 0;

    canvas.onmousemove = (e) => {
        const rect = canvas.getBoundingClientRect();
        p1Y = e.clientY - rect.top - 40;
    };

    let timer = setInterval(() => {
        if (document.getElementById('miniGameModal').style.display === 'none') {
            clearInterval(timer); return;
        }

        ballX += bx; ballY += by;
        p2Y += (ballY - (p2Y + 40)) * 0.1;

        if (ballY < 0 || ballY > 400) by *= -1;

        if (ballX < 20 && ballY > p1Y && ballY < p1Y + 80) { bx *= -1; score += 5; scoreText.textContent = 'Skor: ' + score; }
        if (ballX > 580 && ballY > p2Y && ballY < p2Y + 80) bx *= -1;

        if (ballX < 0 || ballX > 600) { ballX = 300; ballY = 200; }

        ctx.fillStyle = '#000'; ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#00f2fe'; ctx.fillRect(10, p1Y, 10, 80);
        ctx.fillStyle = '#ff007f'; ctx.fillRect(580, p2Y, 10, 80);
        ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(ballX, ballY, 8, 0, Math.PI * 2); ctx.fill();
    }, 16);
}

// Brick Breaker Mini-Game
function startBreakerGame(canvas, scoreText) {
    const ctx = canvas.getContext('2d');
    let paddleX = 250, ballX = 300, ballY = 300, dx = 3, dy = -3, score = 0;
    let bricks = [];

    for (let r = 0; r < 4; r++) {
        for (let c = 0; c < 8; c++) {
            bricks.push({x: c * 70 + 20, y: r * 25 + 30, active: true});
        }
    }

    canvas.onmousemove = (e) => {
        const rect = canvas.getBoundingClientRect();
        paddleX = e.clientX - rect.left - 50;
    };

    let timer = setInterval(() => {
        if (document.getElementById('miniGameModal').style.display === 'none') {
            clearInterval(timer); return;
        }

        ballX += dx; ballY += dy;
        if (ballX < 0 || ballX > 600) dx *= -1;
        if (ballY < 0) dy *= -1;

        if (ballY > 370 && ballX > paddleX && ballX < paddleX + 100) dy *= -1;

        bricks.forEach(b => {
            if (b.active && ballX > b.x && ballX < b.x + 60 && ballY > b.y && ballY < b.y + 20) {
                b.active = false; dy *= -1; score += 20; scoreText.textContent = 'Skor: ' + score;
            }
        });

        ctx.fillStyle = '#000'; ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#00f2fe'; ctx.fillRect(paddleX, 380, 100, 12);
        ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(ballX, ballY, 7, 0, Math.PI * 2); ctx.fill();

        bricks.forEach(b => {
            if (b.active) {
                ctx.fillStyle = '#f59e0b'; ctx.fillRect(b.x, b.y, 60, 20);
            }
        });
    }, 16);
}

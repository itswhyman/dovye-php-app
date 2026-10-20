/* TurkBil Oyun Fuarı — DGRWEB.IO Master Agario Engine v7.0 */

let playerNick = 'Oyuncu';
let playerAvatarImg = null;

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
    if (input.length < 2) {
        alert('Lütfen en az 2 karakterli bir nickname girin!');
        return;
    }
    playerNick = input;
    document.getElementById('activeUserBadge').textContent = 'Oyuncu: ' + playerNick;
    document.getElementById('nickOverlay').style.display = 'none';
}

// GAME ENGINE CORE
let canvas, ctx, miniCanvas, miniCtx;
let gameRunning = false;
const MAP_SIZE = 6000;

let mouseX = 0, mouseY = 0;
let isSprinting = false;

// Player Cells Array (Support multi-cell splitting)
let playerCells = [];
let foodPellets = [];
let redViruses = [];
let fireballs = [];
let botPlayers = [];

class Cell {
    constructor(x, y, radius, color, isPlayer = false, nick = '') {
        this.x = x;
        this.y = y;
        this.radius = radius;
        this.color = color;
        this.isPlayer = isPlayer;
        this.nick = nick;
        this.vx = 0;
        this.vy = 0;
        this.remergeTimer = 0;
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
        if (rand < 0.6) {
            this.type = 'blue'; // 1 pt
            this.points = 1;
            this.radius = 5;
            this.color = '#00f2fe';
        } else if (rand < 0.85) {
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
        this.x = x || Math.random() * (MAP_SIZE - 200) + 100;
        this.y = y || Math.random() * (MAP_SIZE - 200) + 100;
        this.radius = 45;
        this.spikes = 16;
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
    playerCells = [new Cell(MAP_SIZE / 2, MAP_SIZE / 2, 20, '#00f2fe', true, playerNick)];

    // Init Food (1200 pellets)
    foodPellets = [];
    for (let i = 0; i < 1200; i++) foodPellets.push(new Food());

    // Init Red Viruses (35 items)
    redViruses = [];
    for (let i = 0; i < 35; i++) redViruses.push(new Virus());

    // Init Bot Competitors (20 Bots)
    botPlayers = [];
    const botNames = ['CyberBot_X', 'NeoMatrix', 'AlphaBlob', 'SiberWolf', 'MegaByte', 'TitanGamer', 'ShadowRider'];
    for (let i = 0; i < 20; i++) {
        const name = botNames[i % botNames.length] + '_' + (i + 1);
        const radius = Math.floor(Math.random() * 40) + 15;
        botPlayers.push(new Cell(Math.random() * MAP_SIZE, Math.random() * MAP_SIZE, radius, '#ff007f', false, name));
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
            if (cell.radius >= 22) { // min limit
                cell.radius /= 1.414;
                let angle = Math.atan2(mouseY - canvas.height / 2, mouseX - canvas.width / 2);
                let splitCell = new Cell(cell.x, cell.y, cell.radius, cell.color, true, cell.nick);
                splitCell.vx = Math.cos(angle) * 18;
                splitCell.vy = Math.sin(angle) * 18;
                splitCell.remergeTimer = 300;
                newCells.push(splitCell);
            }
        });
        playerCells.push(...newCells);
    }

    // W: Eject Food (+2 pt)
    if (key === 'w') {
        playerCells.forEach(cell => {
            if (cell.radius > 16) {
                cell.radius = Math.max(12, Math.sqrt(cell.radius * cell.radius - 20));
                let angle = Math.atan2(mouseY - canvas.height / 2, mouseX - canvas.width / 2);
                let f = new Food();
                f.x = cell.x + Math.cos(angle) * (cell.radius + 15);
                f.y = cell.y + Math.sin(angle) * (cell.radius + 15);
                f.type = 'yellow';
                f.points = 2;
                f.color = '#f59e0b';
                f.radius = 7;
                foodPellets.push(f);
            }
        });
    }

    // V: Fireball Attack! (Forces Target to Split)
    if (key === 'v') {
        if (playerCells[0] && playerCells[0].radius > 20) {
            let angle = Math.atan2(mouseY - canvas.height / 2, mouseX - canvas.width / 2);
            fireballs.push({
                x: playerCells[0].x + Math.cos(angle) * (playerCells[0].radius + 10),
                y: playerCells[0].y + Math.sin(angle) * (playerCells[0].radius + 10),
                vx: Math.cos(angle) * 22,
                vy: Math.sin(angle) * 22,
                radius: 12
            });
        }
    }

    // B: Sprint Hold
    if (key === 'b') {
        isSprinting = true;
    }

    // C: Secret Instant Merge!
    if (key === 'c') {
        if (playerCells.length > 1) {
            let totalArea = 0;
            playerCells.forEach(c => totalArea += c.radius * c.radius);
            playerCells[0].radius = Math.sqrt(totalArea);
            playerCells = [playerCells[0]];
        }
    }
}

function resizeCanvas() {
    if (!canvas) return;
    canvas.width = canvas.parentElement.clientWidth;
    canvas.height = canvas.parentElement.clientHeight;
}

let lastSprintBurn = 0;

function gameLoop(time) {
    if (!gameRunning) return;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Calculate Center of Mass for Camera
    let avgX = 0, avgY = 0, totalR = 0;
    playerCells.forEach(c => {
        avgX += c.x;
        avgY += c.y;
        totalR += c.radius;
    });
    avgX /= (playerCells.length || 1);
    avgY /= (playerCells.length || 1);

    // Dynamic Camera View Zoom
    let scale = Math.max(0.35, Math.min(1.2, 35 / (totalR + 10)));
    
    ctx.save();
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.scale(scale, scale);
    ctx.translate(-avgX, -avgY);

    // Draw Map Grid Lines & Coordinates
    ctx.strokeStyle = 'rgba(0, 242, 254, 0.08)';
    ctx.lineWidth = 1;
    for (let x = 0; x <= MAP_SIZE; x += 100) {
        ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, MAP_SIZE); ctx.stroke();
    }
    for (let y = 0; y <= MAP_SIZE; y += 100) {
        ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(MAP_SIZE, y); ctx.stroke();
    }

    // Draw Map Border
    ctx.strokeStyle = '#00f2fe';
    ctx.lineWidth = 6;
    ctx.strokeRect(0, 0, MAP_SIZE, MAP_SIZE);

    // Sprint Logic (B key burns mass for 2x speed)
    let speedMult = 1.0;
    if (isSprinting && time - lastSprintBurn > 1000) {
        speedMult = 2.2;
        playerCells.forEach(c => {
            if (c.radius > 15) c.radius = Math.max(12, c.radius - 1.2);
        });
        lastSprintBurn = time;
    }

    // Update Player Movement towards Cursor
    playerCells.forEach(c => {
        let targetX = avgX + (mouseX - canvas.width / 2) / scale;
        let targetY = avgY + (mouseY - canvas.height / 2) / scale;

        let dx = targetX - c.x;
        let dy = targetY - c.y;
        let dist = Math.hypot(dx, dy);

        let baseSpeed = Math.max(2.2, (80 / c.radius)) * speedMult;
        if (dist > 5) {
            c.x += (dx / dist) * baseSpeed + c.vx;
            c.y += (dy / dist) * baseSpeed + c.vy;
        }

        c.vx *= 0.92;
        c.vy *= 0.92;

        // Clamp inside map
        c.x = Math.max(c.radius, Math.min(MAP_SIZE - c.radius, c.x));
        c.y = Math.max(c.radius, Math.min(MAP_SIZE - c.radius, c.y));

        if (c.remergeTimer > 0) c.remergeTimer--;
    });

    // Draw Food Pellets
    foodPellets.forEach(f => {
        ctx.beginPath();
        ctx.arc(f.x, f.y, f.radius, 0, Math.PI * 2);
        ctx.fillStyle = f.color;
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

    // Update & Draw Fireballs
    fireballs.forEach((fb, idx) => {
        fb.x += fb.vx;
        fb.y += fb.vy;

        ctx.beginPath();
        ctx.arc(fb.x, fb.y, fb.radius, 0, Math.PI * 2);
        ctx.fillStyle = '#ff007f';
        ctx.shadowBlur = 15;
        ctx.shadowColor = '#ff007f';
        ctx.fill();
        ctx.shadowBlur = 0;

        // Check Collision with Bots
        botPlayers.forEach(bot => {
            if (Math.hypot(bot.x - fb.x, bot.y - fb.y) < bot.radius + fb.radius) {
                // Split bot into two!
                bot.radius /= 1.414;
                botPlayers.push(new Cell(bot.x + 30, bot.y + 30, bot.radius, '#ff007f', false, bot.nick + '_2'));
                fireballs.splice(idx, 1);
            }
        });
    });

    // Update & Draw Bots
    botPlayers.forEach(bot => {
        // Random wandering AI
        bot.x += (Math.random() - 0.5) * 3;
        bot.y += (Math.random() - 0.5) * 3;
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

    // Draw Player Cells & Avatar Image Masking
    playerCells.forEach(c => {
        ctx.save();
        ctx.beginPath();
        ctx.arc(c.x, c.y, c.radius, 0, Math.PI * 2);
        ctx.clip();

        if (playerAvatarImg) {
            ctx.drawImage(playerAvatarImg, c.x - c.radius, c.y - c.radius, c.radius * 2, c.radius * 2);
        } else {
            ctx.fillStyle = c.color;
            ctx.fill();
        }
        ctx.restore();

        // Stroke Border
        ctx.beginPath();
        ctx.arc(c.x, c.y, c.radius, 0, Math.PI * 2);
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3;
        ctx.stroke();

        // Nick Label
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold ' + Math.max(12, c.radius / 2.5) + 'px Plus Jakarta Sans';
        ctx.textAlign = 'center';
        ctx.fillText(c.nick, c.x, c.y + 4);
    });

    // Check Food Collisions
    playerCells.forEach(c => {
        foodPellets.forEach((f, idx) => {
            if (Math.hypot(c.x - f.x, c.y - f.y) < c.radius + f.radius) {
                c.radius += f.points * 0.18; // Increase mass/size
                foodPellets[idx] = new Food(); // Respawn
            }
        });

        // Check Virus Collisions (Splits cell if cell is larger)
        redViruses.forEach(v => {
            if (c.radius > v.radius && Math.hypot(c.x - v.x, c.y - v.y) < c.radius) {
                c.radius /= 1.5;
                for (let k = 0; k < 3; k++) {
                    let split = new Cell(c.x + (Math.random() - 0.5) * 60, c.y + (Math.random() - 0.5) * 60, c.radius, c.color, true, c.nick);
                    split.remergeTimer = 400;
                    playerCells.push(split);
                }
            }
        });
    });

    ctx.restore();

    // Render Glassmorphism Minimap
    renderMinimap(avgX, avgY);

    // Update Live Leaderboard
    updateLeaderboard();

    requestAnimationFrame(gameLoop);
}

function renderMinimap(playerX, playerY) {
    miniCtx.clearRect(0, 0, 180, 180);

    let scale = 180 / MAP_SIZE;

    // Draw Player Dot
    miniCtx.fillStyle = '#00f2fe';
    miniCtx.beginPath();
    miniCtx.arc(playerX * scale, playerY * scale, 4, 0, Math.PI * 2);
    miniCtx.fill();

    // Draw Bots Dots
    miniCtx.fillStyle = '#ff007f';
    botPlayers.forEach(b => {
        miniCtx.beginPath();
        miniCtx.arc(b.x * scale, b.y * scale, 2, 0, Math.PI * 2);
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
    allScores.slice(0, 5).forEach((item, idx) => {
        html += `<div class="leader-item"><span>${idx + 1}. ${item.nick}</span><span>${item.score}</span></div>`;
    });

    document.getElementById('leaderList').innerHTML = html;
}

function launchMiniGame(name) {
    alert(name.toUpperCase() + ' Oyunu Başlatılıyor! TurkBil Oyun Fuarı Keyifli Oyunlar Diler.');
}

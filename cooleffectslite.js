(function() {
    // Disable on small mobile devices to optimize CPU/battery usage
    if (window.innerWidth < 768) return;

    // Create fixed full-screen canvas
    const canvas = document.createElement('canvas');
    canvas.id = 'cooleffectslite-canvas';
    Object.assign(canvas.style, {
        position: 'fixed',
        top: '0',
        left: '0',
        width: '100vw',
        height: '100vh',
        zIndex: '-999',
        pointerEvents: 'none' // Ensures background clicks bleed through to dashboard
    });
    document.body.prepend(canvas);

    const ctx = canvas.getContext('2d');
    let width = canvas.width = window.innerWidth;
    let height = canvas.height = window.innerHeight;

    const particles = [];
    // Scale particle count based on display size to avoid performance bottlenecks
    const maxParticles = Math.min(90, Math.floor((width * height) / 14000));
    const connectionDist = 120;
    const mouse = { x: null, y: null, radius: 150 };

    window.addEventListener('mousemove', (e) => {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
    });

    window.addEventListener('mouseout', () => {
        mouse.x = null;
        mouse.y = null;
    });

    window.addEventListener('resize', () => {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    });

    class Particle {
        constructor() {
            this.x = Math.random() * width;
            this.y = Math.random() * height;
            // Gentle horizontal drift, upward vertical movement
            this.vx = (Math.random() - 0.5) * 0.3;
            this.vy = -(Math.random() * 0.4 + 0.1); 
            this.radius = Math.random() * 1.8 + 0.8;
            
            // Harmonious HSL colors based on the mauve purple theme
            const colors = [
                'rgba(224, 176, 255, 0.35)', // Mauve
                'rgba(168, 85, 247, 0.25)',  // Purple light
                'rgba(192, 132, 252, 0.3)'   // Lavender
            ];
            this.color = colors[Math.floor(Math.random() * colors.length)];
        }

        update() {
            // Hover/Attraction effect: pull particles gently towards mouse
            if (mouse.x !== null && mouse.y !== null) {
                const dx = mouse.x - this.x;
                const dy = mouse.y - this.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < mouse.radius) {
                    const force = (mouse.radius - dist) / mouse.radius;
                    this.x += (dx / dist) * force * 1.2;
                    this.y += (dy / dist) * force * 1.2;
                }
            }

            // Normal motion
            this.x += this.vx;
            this.y += this.vy;

            // Boundaries wrapping
            if (this.x < 0) this.x = width;
            if (this.x > width) this.x = 0;
            
            // Loop vertical coordinates (float out top -> respawn at bottom)
            if (this.y < 0) {
                this.y = height;
                this.x = Math.random() * width;
            }
        }

        draw() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.fillStyle = this.color;
            ctx.fill();
        }
    }

    // Populate particles
    for (let i = 0; i < maxParticles; i++) {
        particles.push(new Particle());
    }

    // Canvas animation loop
    function animate() {
        ctx.clearRect(0, 0, width, height);

        // Draw connecting networks
        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const p1 = particles[i];
                const p2 = particles[j];
                const dx = p1.x - p2.x;
                const dy = p1.y - p2.y;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < connectionDist) {
                    const alpha = (1 - dist / connectionDist) * 0.12;
                    ctx.beginPath();
                    ctx.moveTo(p1.x, p1.y);
                    ctx.lineTo(p2.x, p2.y);
                    ctx.strokeStyle = `rgba(224, 176, 255, ${alpha})`;
                    ctx.lineWidth = 0.8;
                    ctx.stroke();
                }
            }
        }

        // Draw and update node entities
        particles.forEach(p => {
            p.update();
            p.draw();
        });

        requestAnimationFrame(animate);
    }

    animate();
})();

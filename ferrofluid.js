document.addEventListener("DOMContentLoaded", () => {
    const container = document.querySelector('.ferrofluid-bg');
    if (!container) return;

    const numBlobs = 15;
    const blobs = [];
    
    // Create the central magnetic core that follows the mouse
    const core = document.createElement('div');
    core.className = 'ff-blob core';
    core.style.width = '200px';
    core.style.height = '200px';
    container.appendChild(core);

    // Create orbiting ferrofluid droplets
    for (let i = 0; i < numBlobs; i++) {
        const blob = document.createElement('div');
        blob.className = 'ff-blob';
        const size = Math.random() * 80 + 40;
        blob.style.width = `${size}px`;
        blob.style.height = `${size}px`;
        container.appendChild(blob);
        
        blobs.push({
            el: blob,
            x: window.innerWidth / 2,
            y: window.innerHeight / 2,
            vx: (Math.random() - 0.5) * 10,
            vy: (Math.random() - 0.5) * 10,
            angle: Math.random() * Math.PI * 2,
            speed: Math.random() * 0.05 + 0.02,
            radius: Math.random() * 100 + 50
        });
    }

    let mouseX = window.innerWidth / 2;
    let mouseY = window.innerHeight / 2;
    let targetX = mouseX;
    let targetY = mouseY;

    window.addEventListener('mousemove', (e) => {
        targetX = e.clientX;
        targetY = e.clientY;
    });

    function animate() {
        // Core follows mouse with ease
        mouseX += (targetX - mouseX) * 0.05;
        mouseY += (targetY - mouseY) * 0.05;
        
        core.style.transform = `translate(${mouseX - 100}px, ${mouseY - 100}px)`;

        // Droplets orbit the core like magnetic spikes
        blobs.forEach(b => {
            b.angle += b.speed;
            
            // Calculate orbital position around the core
            const targetOrbX = mouseX + Math.cos(b.angle) * b.radius;
            const targetOrbY = mouseY + Math.sin(b.angle) * b.radius;
            
            // Ease towards orbit
            b.x += (targetOrbX - b.x) * 0.1;
            b.y += (targetOrbY - b.y) * 0.1;
            
            b.el.style.transform = `translate(${b.x - parseInt(b.el.style.width)/2}px, ${b.y - parseInt(b.el.style.height)/2}px)`;
        });

        requestAnimationFrame(animate);
    }
    
    animate();
});
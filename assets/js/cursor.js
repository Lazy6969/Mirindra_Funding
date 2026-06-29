// ============================================
// CURSEUR PERSONNALISÉ AVANCÉ
// ============================================

class CustomCursor {
    constructor() {
        this.cursor = null;
        this.dot = null;
        this.pos = { x: 0, y: 0 };
        this.mouse = { x: 0, y: 0 };
        this.speed = 0.15; // Un peu plus réactif
        this.isHovering = false;
        this.isClicking = false;
        this.trails = [];
        
        this.init();
    }
    
    init() {
        // Créer les éléments du curseur
        this.createElements();
        
        // Événements
        document.addEventListener('mousemove', (e) => this.handleMouseMove(e));
        document.addEventListener('mousedown', () => this.handleMouseDown());
        document.addEventListener('mouseup', () => this.handleMouseUp());
        
        // Détection des éléments interactifs
        this.setupInteractiveElements();
        
        // Animation loop
        this.animate();
    }
    
    createElements() {
        // Curseur principal
        this.cursor = document.createElement('div');
        this.cursor.className = 'custom-cursor';
        document.body.appendChild(this.cursor);
        
        // Point central
        this.dot = document.createElement('div');
        this.dot.className = 'cursor-dot';
        document.body.appendChild(this.dot);

        // Traînées
        for (let i = 1; i <= 2; i++) {
            const trail = document.createElement('div');
            trail.className = `cursor-trail trail-${i}`;
            document.body.appendChild(trail);
            this.trails.push(trail);
        }
    }
    
    handleMouseMove(e) {
        this.mouse.x = e.clientX;
        this.mouse.y = e.clientY;
    }
    
    handleMouseDown() {
        this.isClicking = true;
        this.cursor.classList.add('click');
        this.createClickWave(this.mouse.x, this.mouse.y);
        this.createClickParticles(this.mouse.x, this.mouse.y);
    }
    
    handleMouseUp() {
        this.isClicking = false;
        this.cursor.classList.remove('click');
    }
    
    setupInteractiveElements() {
        const interactiveElements = document.querySelectorAll(
            'a, button, .btn, input, textarea, select, [role="button"], .project-card, .category-card, .stat-card'
        );
        
        interactiveElements.forEach(el => {
            el.addEventListener('mouseenter', () => {
                this.isHovering = true;
                this.cursor.classList.add('hover');
                // Si l'élément est un champ de texte, masquer le curseur personnalisé
                if (el.matches('input[type="text"], input[type="email"], input[type="password"], input:not([type="button"]):not([type="submit"]):not([type="reset"]), textarea, select')) {
                    document.body.classList.add('hide-custom-cursor');
                }
            });
            
            el.addEventListener('mouseleave', () => {
                this.isHovering = false;
                this.cursor.classList.remove('hover');
                // Si l'élément est un champ de texte, réafficher le curseur personnalisé
                if (el.matches('input[type="text"], input[type="email"], input[type="password"], input:not([type="button"]):not([type="submit"]):not([type="reset"]), textarea, select')) {
                    document.body.classList.remove('hide-custom-cursor');
                }
            });
        });
    }
    
    createClickWave(x, y) {
        const wave = document.createElement('div');
        wave.className = 'click-wave';
        wave.style.left = x + 'px';
        wave.style.top = y + 'px';
        document.body.appendChild(wave);
        
        setTimeout(() => wave.remove(), 600);
    }
    
    createClickParticles(x, y) {
        const particleCount = 8;
        
        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'click-particle';
            particle.style.left = x + 'px';
            particle.style.top = y + 'px';
            
            const angle = (i / particleCount) * Math.PI * 2;
            const velocity = 50 + Math.random() * 50;
            const tx = Math.cos(angle) * velocity;
            const ty = Math.sin(angle) * velocity;
            
            particle.style.setProperty('--tx', tx + 'px');
            particle.style.setProperty('--ty', ty + 'px');
            
            document.body.appendChild(particle);
            setTimeout(() => particle.remove(), 600);
        }
    }
    
    animate() {
        // Interpolation fluide
        this.pos.x += (this.mouse.x - this.pos.x) * this.speed;
        this.pos.y += (this.mouse.y - this.pos.y) * this.speed;
        
        // Mise à jour des positions
        this.cursor.style.left = this.pos.x + 'px';
        this.cursor.style.top = this.pos.y + 'px';
        this.dot.style.left = this.mouse.x + 'px'; // Le point suit la souris sans délai
        this.dot.style.top = this.mouse.y + 'px';

        // Mise à jour des traînées
        this.trails.forEach((trail, index) => {
            // Ajout d'un léger décalage pour chaque traînée
            trail.style.left = this.pos.x + 'px';
            trail.style.top = this.pos.y + 'px';
        });
        
        requestAnimationFrame(() => this.animate());
    }
}

// Initialiser le curseur lorsque le DOM est chargé
document.addEventListener('DOMContentLoaded', () => {
    if (window.innerWidth > 768) {
        new CustomCursor();
    }
});
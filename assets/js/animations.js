// ============================================
// ANIMATIONS AU SCROLL ET INTERACTIONS
// ============================================

class AnimationManager {
    constructor() {
        this.observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        this.init();
    }
    
    init() {
        // Observer pour les animations au scroll
        this.setupScrollObserver();
        
        // Animation des compteurs
        this.setupCounters();
        
        // Effet parallax
        this.setupParallax();
        
        // Navigation scroll effect
        this.setupNavbar();
        
        // Smooth scroll
        this.setupSmoothScroll();
    }
    
    setupScrollObserver() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    
                    // Animation spécifique pour les compteurs
                    if (entry.target.classList.contains('stat-number')) {
                        this.animateCounter(entry.target);
                    }
                }
            });
        }, this.observerOptions);
        
        // Observer tous les éléments animables
        document.querySelectorAll('.animate-on-scroll, .stat-number, .project-card, .category-card').forEach(el => {
            observer.observe(el);
        });
    }
    
    setupCounters() {
        const counters = document.querySelectorAll('.counter');
        
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-target'));
            const duration = 2000;
            const step = target / (duration / 16);
            let current = 0;
            
            const updateCounter = () => {
                current += step;
                if (current < target) {
                    counter.textContent = Math.floor(current);
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.textContent = target;
                }
            };
            
            // Démarrer l'animation quand l'élément est visible
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        updateCounter();
                        observer.unobserve(entry.target);
                    }
                });
            });
            
            observer.observe(counter);
        });
    }
    
    animateCounter(element) {
        const target = parseInt(element.getAttribute('data-target'));
        const duration = 2000;
        const start = 0;
        const startTime = performance.now();
        
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const current = Math.floor(start + (target - start) * easeOutQuart);
            
            element.textContent = current.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }
    
    setupParallax() {
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallaxElements = document.querySelectorAll('.parallax');
            
            parallaxElements.forEach(el => {
                const speed = el.getAttribute('data-speed') || 0.5;
                el.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });
    }
    
    setupNavbar() {
        const navbar = document.querySelector('.navbar');
        let lastScroll = 0;
        
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            
            lastScroll = currentScroll;
        });
    }
    
    setupSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }
}

// Effets magnétiques sur les boutons
class MagneticEffect {
    constructor(element) {
        this.element = element;
        this.init();
    }
    
    init() {
        this.element.addEventListener('mousemove', (e) => {
            const rect = this.element.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            
            this.element.style.transform = `translate(${x * 0.3}px, ${y * 0.3}px)`;
        });
        
        this.element.addEventListener('mouseleave', () => {
            this.element.style.transform = 'translate(0, 0)';
        });
    }
}

// Initialiser les effets magnétiques
document.addEventListener('DOMContentLoaded', () => {
    new AnimationManager();
    
    // Appliquer l'effet magnétique aux boutons
    document.querySelectorAll('.magnetic-btn').forEach(btn => {
        new MagneticEffect(btn);
    });
    
    // Animation de texte révélation
    document.querySelectorAll('.text-reveal').forEach(el => {
        const text = el.textContent;
        el.innerHTML = '';
        
        text.split('').forEach((char, index) => {
            const span = document.createElement('span');
            span.textContent = char === ' ' ? '\u00A0' : char;
            span.style.animationDelay = `${index * 0.05}s`;
            el.appendChild(span);
        });
    });
});

// Effet de particules en arrière-plan
function createParticles(container, count = 50) {
    for (let i = 0; i < count; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 10 + 's';
        particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
        container.appendChild(particle);
    }
}

// Initialiser les particules si nécessaire
document.addEventListener('DOMContentLoaded', () => {
    const heroSection = document.querySelector('.hero');
    if (heroSection) {
        const particlesContainer = document.createElement('div');
        particlesContainer.className = 'particles';
        heroSection.appendChild(particlesContainer);
        createParticles(particlesContainer, 30);
    }
});
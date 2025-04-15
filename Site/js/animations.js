// Inicialização AOS
AOS.init({
    duration: 1000,
    once: false,
    mirror: true,
    easing: 'ease-in-out-quad'
});

// Animação GSAP para efeito de carga
document.addEventListener('DOMContentLoaded', () => {
    gsap.from('.main-header', {
        duration: 1.5,
        y: -100,
        opacity: 0,
        ease: 'power4.out'
    });

    gsap.from('.shard-section', {
        stagger: 0.2,
        duration: 1.5,
        scale: 0.95,
        opacity: 0,
        ease: 'expo.out'
    });
});

// Intersection Observer para animações CSS-only
const shardObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if(entry.isIntersecting) {
            entry.target.classList.add('shard-visible');
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.shard-section').forEach(section => {
    shardObserver.observe(section);
});

// Interatividade do cursor
document.addEventListener('mousemove', (e) => {
    const x = e.clientX / window.innerWidth;
    const y = e.clientY / window.innerHeight;
    
    document.querySelectorAll('.shard-section').forEach(section => {
        section.style.transform = `perspective(1000px) rotateX(${y * 10}deg) rotateY(${x * 10}deg)`;
    });
});
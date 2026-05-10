const loader = document.getElementById('pageLoader');
window.addEventListener('load', () => {
    setTimeout(() => {
        loader.classList.add('loader-hidden');
    }, 650);
});

const mobileMenu = document.getElementById('mobileMenu');
const menuToggle = document.getElementById('menuToggle');
if (menuToggle && mobileMenu) {
    menuToggle.addEventListener('click', () => {
        const isOpen = mobileMenu.style.maxHeight && mobileMenu.style.maxHeight !== '0px';
        mobileMenu.style.maxHeight = isOpen ? '0px' : mobileMenu.scrollHeight + 'px';
        menuToggle.setAttribute('aria-expanded', String(!isOpen));
    });
}

document.querySelectorAll('#mobileMenu a').forEach((link) => {
    link.addEventListener('click', () => {
        if (mobileMenu && menuToggle) {
            mobileMenu.style.maxHeight = '0px';
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    });
});

document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (event) => {
        const targetId = anchor.getAttribute('href');
        const target = document.querySelector(targetId);
        if (!target) {
            return;
        }
        event.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

document.querySelectorAll('.ripple-btn').forEach((button) => {
    button.addEventListener('click', (event) => {
        const circle = document.createElement('span');
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;
        const rect = button.getBoundingClientRect();
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - rect.left - radius}px`;
        circle.style.top = `${event.clientY - rect.top - radius}px`;
        circle.className = 'ripple';
        const existing = button.querySelector('.ripple');
        if (existing) {
            existing.remove();
        }
        button.appendChild(circle);
    });
});

const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.18 });

document.querySelectorAll('.reveal').forEach((element) => {
    revealObserver.observe(element);
});

const counters = document.querySelectorAll('.counter');
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (!entry.isIntersecting) {
            return;
        }
        const counter = entry.target;
        const target = Number(counter.dataset.target || 0);
        const duration = 1800;
        const startTime = performance.now();

        function updateCounter(now) {
            const progress = Math.min((now - startTime) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            counter.textContent = Math.floor(target * eased).toLocaleString();
            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target.toLocaleString();
            }
        }

        requestAnimationFrame(updateCounter);
        counterObserver.unobserve(counter);
    });
}, { threshold: 0.5 });

counters.forEach((counter) => counterObserver.observe(counter));

const stepCards = document.querySelectorAll('[data-step-card]');
const progressLine = document.getElementById('progressLine');
const stepIndicator = document.getElementById('stepIndicator');
const stepObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (!entry.isIntersecting) {
            return;
        }
        const step = Number(entry.target.dataset.stepCard || 1);
        progressLine.style.width = `${step * 33.333}%`;
        stepIndicator.textContent = `Step ${step} of 3`;
    });
}, { threshold: 0.65 });

stepCards.forEach((card) => stepObserver.observe(card));

const testimonialTrack = document.getElementById('testimonialTrack');
const dots = document.querySelectorAll('.testimonial-dot');
let activeSlide = 0;
let testimonialTimer;

function renderSlide(index) {
    activeSlide = index;
    testimonialTrack.style.transform = `translateX(-${index * 100}%)`;
    dots.forEach((dot, dotIndex) => {
        dot.classList.toggle('bg-white/10', dotIndex !== index);
        dot.classList.toggle('bg-gradient-to-r', dotIndex === index);
        dot.classList.toggle('from-indigo-400', dotIndex === index);
        dot.classList.toggle('to-emerald-300', dotIndex === index);
    });
}

function startTestimonials() {
    testimonialTimer = setInterval(() => {
        renderSlide((activeSlide + 1) % dots.length);
    }, 4200);
}

dots.forEach((dot) => {
    dot.addEventListener('click', () => {
        clearInterval(testimonialTimer);
        renderSlide(Number(dot.dataset.slide || 0));
        startTestimonials();
    });
});

renderSlide(0);
startTestimonials();

const heroParallax = document.getElementById('heroParallax');
window.addEventListener('mousemove', (event) => {
    if (window.innerWidth < 1024) {
        return;
    }
    const x = (event.clientX / window.innerWidth - 0.5) * 18;
    const y = (event.clientY / window.innerHeight - 0.5) * 18;
    heroParallax.style.transform = `translate3d(${x}px, ${y}px, 0)`;
});

document.querySelectorAll('[data-tilt]').forEach((card) => {
    card.addEventListener('mousemove', (event) => {
        if (window.innerWidth < 1024) {
            return;
        }
        const rect = card.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        const rotateX = ((y / rect.height) - 0.5) * -8;
        const rotateY = ((x / rect.width) - 0.5) * 10;
        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px)`;
    });

    card.addEventListener('mouseleave', () => {
        card.style.transform = '';
    });
});

const cursor = document.querySelector('.custom-cursor');
const cursorRing = document.querySelector('.custom-cursor-ring');
window.addEventListener('mousemove', (event) => {
    cursor.style.left = `${event.clientX}px`;
    cursor.style.top = `${event.clientY}px`;
    cursorRing.style.left = `${event.clientX}px`;
    cursorRing.style.top = `${event.clientY}px`;
});

document.querySelectorAll('.interactive').forEach((element) => {
    element.addEventListener('mouseenter', () => document.body.classList.add('cursor-active'));
    element.addEventListener('mouseleave', () => document.body.classList.remove('cursor-active'));
});

const particleContainer = document.getElementById('particles');
const particleCount = window.innerWidth < 768 ? 18 : 34;
for (let i = 0; i < particleCount; i += 1) {
    const particle = document.createElement('span');
    const size = Math.random() * 4 + 2;
    particle.style.position = 'absolute';
    particle.style.width = `${size}px`;
    particle.style.height = `${size}px`;
    particle.style.left = `${Math.random() * 100}%`;
    particle.style.top = `${Math.random() * 100}%`;
    particle.style.borderRadius = '9999px';
    particle.style.background = i % 3 === 0 ? 'rgba(129,140,248,0.55)' : (i % 3 === 1 ? 'rgba(167,139,250,0.45)' : 'rgba(52,211,153,0.35)');
    particle.style.boxShadow = '0 0 22px currentColor';
    particle.style.opacity = (Math.random() * 0.55 + 0.15).toFixed(2);
    particle.style.animation = `float ${Math.random() * 5 + 5}s ease-in-out ${Math.random() * -6}s infinite`;
    particleContainer.appendChild(particle);
}

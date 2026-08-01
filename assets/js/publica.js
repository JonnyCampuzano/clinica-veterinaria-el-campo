const mobileButton = document.getElementById('mobileNavButton');
const navLinks = document.getElementById('publicNavLinks');

if (mobileButton && navLinks) {
    mobileButton.addEventListener('click', () => {
        navLinks.classList.toggle('open');
    });

    navLinks.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => navLinks.classList.remove('open'));
    });
}

const sections = [...document.querySelectorAll('main section[id]')];
const menuLinks = [...document.querySelectorAll('.nav-links a[href^="#"]')];

const updateActiveLink = () => {
    let currentId = 'inicio';

    sections.forEach((section) => {
        if (window.scrollY >= section.offsetTop - 150) {
            currentId = section.id;
        }
    });

    menuLinks.forEach((link) => {
        link.classList.toggle('active', link.getAttribute('href') === `#${currentId}`);
    });
};

window.addEventListener('scroll', updateActiveLink, { passive: true });
updateActiveLink();

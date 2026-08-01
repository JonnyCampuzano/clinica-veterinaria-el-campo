const sidebar = document.getElementById('sidebar');
const menuButton = document.getElementById('menuButton');

if (menuButton && sidebar) {
    menuButton.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });

    document.addEventListener('click', (event) => {
        const clickedOutside = !sidebar.contains(event.target) && !menuButton.contains(event.target);

        if (clickedOutside) {
            sidebar.classList.remove('open');
        }
    });
}

document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
        const message = element.dataset.confirm || '¿Deseas continuar?';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

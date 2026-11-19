document.addEventListener("DOMContentLoaded", () => {
    const cards = document.querySelectorAll('.card, .stat-card, .section-card, .balance-card, .bento-card');
    cards.forEach(card => {
        // Prevent double wrapping
        if (!card.querySelector('.star-border-wrapper')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'star-border-wrapper';
            // Insert it as the very first child
            card.insertBefore(wrapper, card.firstChild);
        }
    });
});
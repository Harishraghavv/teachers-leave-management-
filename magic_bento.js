document.addEventListener("DOMContentLoaded", () => {
    // Page Transition Fade-in
    document.body.style.opacity = "0";
    document.body.style.transition = "opacity 0.5s ease-in-out";
    requestAnimationFrame(() => {
        document.body.style.opacity = "1";
    });

    // Magic Bento Mouse Tracker for all cards globally
    document.addEventListener("mousemove", (e) => {
        const cards = document.querySelectorAll('.card, .stat-card, .section-card, .balance-card');
        cards.forEach(card => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            card.style.setProperty("--mouse-x", `${x}px`);
            card.style.setProperty("--mouse-y", `${y}px`);
        });
    });
});
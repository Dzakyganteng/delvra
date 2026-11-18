(() => {
    const storageKey = "delvra-theme";
    const storedTheme = localStorage.getItem(storageKey);
    const initialTheme = storedTheme === "dark" || storedTheme === "light"
        ? storedTheme
        : (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");

    document.documentElement.dataset.theme = initialTheme;

    const addToggle = () => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "theme-toggle";
        button.setAttribute("aria-label", initialTheme === "dark" ? "Aktifkan mode terang" : "Aktifkan mode gelap");
        button.title = initialTheme === "dark" ? "Mode terang" : "Mode gelap";
        button.textContent = initialTheme === "dark" ? "☀" : "☾";

        button.addEventListener("click", () => {
            const nextTheme = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
            document.documentElement.dataset.theme = nextTheme;
            document.body.dataset.theme = nextTheme;
            localStorage.setItem(storageKey, nextTheme);
            button.setAttribute("aria-label", nextTheme === "dark" ? "Aktifkan mode terang" : "Aktifkan mode gelap");
            button.title = nextTheme === "dark" ? "Mode terang" : "Mode gelap";
            button.textContent = nextTheme === "dark" ? "☀" : "☾";
        });

        document.body.dataset.theme = initialTheme;
        document.body.append(button);
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", addToggle, { once: true });
    } else {
        addToggle();
    }
})();

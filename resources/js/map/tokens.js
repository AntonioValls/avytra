/*
 * Brand colours for map marks, read from the Tailwind theme variables that app.css defines
 * (docs/14): no colour literals live in JavaScript.
 */
export function brandTokens() {
    const style = getComputedStyle(document.documentElement);
    const read = (name) => style.getPropertyValue(name).trim();

    return {
        ink: read('--color-ink'),
        transfer: read('--color-transfer'),
    };
}

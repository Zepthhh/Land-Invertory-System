/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        bg: "#0b110e",
        panel: "rgba(18, 27, 22, 0.65)",
        border: "rgba(255, 255, 255, 0.08)",
        primary: "#10b981",
        accent: "#059669"
      }
    },
  },
  plugins: [],
}

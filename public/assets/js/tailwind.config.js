/* =====================================================================
   Safer Cruise — Tailwind theme (loaded right after the Tailwind CDN)
   ---------------------------------------------------------------------
   Brand tokens live here AND in assets/css/theme.css (:root variables).
   To match safer.travel exactly, replace the hex values in both places.
   ===================================================================== */
tailwind.config = {
  theme: {
    extend: {
      colors: {
        ocean: {
          50: '#EEF6FC', 100: '#D9EBF8', 200: '#B3D5F0', 300: '#7FB8E4', 400: '#4797D4',
          500: '#1B72BF', 600: '#155C9E', 700: '#124A80', 800: '#0F3A64', 900: '#0C2D4E'
        },
        lagoon: { 50: '#E7F8FB', 100: '#C9EFF5', 400: '#2BC4D9', 500: '#12A9C2', 600: '#0E8AA0' },
        sun:    { 50: '#FFF4E5', 100: '#FFE3BF', 400: '#FFAD47', 500: '#F7941D', 600: '#DD7A06' },
        ink: '#0F2A44',
        muted: '#5B6B7F',
        line: '#E2EAF2',
        page: '#F5F8FB'
      },
      fontFamily: {
        display: ['Sora', 'IBM Plex Sans Arabic', 'sans-serif'],
        sans: ['Manrope', 'IBM Plex Sans Arabic', 'system-ui', 'sans-serif']
      },
      maxWidth: { site: '1240px' },
      borderRadius: { xl2: '20px' }
    }
  }
};

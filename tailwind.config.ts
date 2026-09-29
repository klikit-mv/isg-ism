import type { Config } from 'tailwindcss';
import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';

const config: Config = {
  darkMode: 'class',
  content: ['./src/**/*.{ts,tsx}'],
  // Button variants are composed from strings (`btn-${variant}`), so keep them.
  safelist: ['btn', 'btn-primary', 'btn-accent', 'btn-secondary', 'btn-danger', 'btn-sm'],
  theme: {
    extend: {
      fontFamily: { sans: ['Figtree', ...defaultTheme.fontFamily.sans] },
      colors: {
        // Brand purple surfaces.
        navy: colors.purple,
        // Emerald accent.
        gold: colors.emerald,
      },
    },
  },
  plugins: [forms],
};

export default config;

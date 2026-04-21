import type { Config } from 'tailwindcss';

/**
 * All colors are exposed as CSS variables in src/styles/tokens.css.
 * Tailwind class names (e.g. `bg-surface`, `text-fg-muted`) reference them
 * so palette changes ripple through without touching components.
 */
const config: Config = {
  content: ['./src/**/*.{js,ts,jsx,tsx,mdx}'],
  theme: {
    extend: {
      colors: {
        bg: 'rgb(var(--color-bg) / <alpha-value>)',
        surface: 'rgb(var(--color-surface) / <alpha-value>)',
        'surface-muted': 'rgb(var(--color-surface-muted) / <alpha-value>)',
        border: 'rgb(var(--color-border) / <alpha-value>)',
        fg: 'rgb(var(--color-fg) / <alpha-value>)',
        'fg-muted': 'rgb(var(--color-fg-muted) / <alpha-value>)',
        primary: 'rgb(var(--color-primary) / <alpha-value>)',
        'primary-hover': 'rgb(var(--color-primary-hover) / <alpha-value>)',
        warning: 'rgb(var(--color-warning) / <alpha-value>)',
        danger: 'rgb(var(--color-danger) / <alpha-value>)',
        'danger-bg': 'rgb(var(--color-danger-bg) / <alpha-value>)',
        success: 'rgb(var(--color-success) / <alpha-value>)',
        heatmap: {
          1: 'rgb(var(--heatmap-1) / <alpha-value>)',
          2: 'rgb(var(--heatmap-2) / <alpha-value>)',
          3: 'rgb(var(--heatmap-3) / <alpha-value>)',
          4: 'rgb(var(--heatmap-4) / <alpha-value>)',
          5: 'rgb(var(--heatmap-5) / <alpha-value>)',
          null: 'rgb(var(--heatmap-null) / <alpha-value>)',
        },
        skillgap: {
          ring: 'rgb(var(--color-skillgap-ring) / <alpha-value>)',
          bg: 'rgb(var(--color-skillgap-bg) / <alpha-value>)',
        },
      },
    },
  },
  plugins: [],
};

export default config;

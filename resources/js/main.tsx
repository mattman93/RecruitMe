import { createRoot } from "react-dom/client";
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import App from "./app";

// Check if this is an Inertia page (has data-page attribute)
const inertiaElement = document.getElementById('app');
if (inertiaElement && inertiaElement.hasAttribute('data-page')) {
  // This is an Inertia page
  createInertiaApp({
    resolve: (name) => resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx')),
    setup({ el, App: InertiaApp, props }) {
      createRoot(el).render(<InertiaApp {...props} />);
    },
  });
} else {
  // This is the main SPA
  const container = document.getElementById("root");
  if (!container) {
    throw new Error("Failed to find the root element");
  }
  const root = createRoot(container);
  root.render(<App />);
}
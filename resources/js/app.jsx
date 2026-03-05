import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';

const root = createRoot(document.getElementById('app'));
root.render(
  <React.StrictMode>
    <div>Restaurant Analytics — React is working ✅</div>
  </React.StrictMode>
);

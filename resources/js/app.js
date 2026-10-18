import './bootstrap';

import Alpine from 'alpinejs';

import levelBuilder from './admin/levelBuilder';

window.Alpine = Alpine;

Alpine.data('levelBuilder', levelBuilder);

Alpine.start();

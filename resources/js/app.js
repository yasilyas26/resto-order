import './bootstrap';

import Alpine from 'alpinejs';

import confirmDialog from './admin/confirmDialog';
import dashboard from './admin/dashboard';
import levelBuilder from './admin/levelBuilder';

window.Alpine = Alpine;

Alpine.data('levelBuilder', levelBuilder);
Alpine.data('adminDashboard', dashboard);
Alpine.data('confirmDialog', confirmDialog);

Alpine.start();

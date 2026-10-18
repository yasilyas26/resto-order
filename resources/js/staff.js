// Entri JavaScript papan karyawan (papan order, riwayat & refund).

import Alpine from 'alpinejs';

import board from './staff/components/board';
import refundHistory from './staff/components/refundHistory';
import { formatRupiah } from './staff/lib/format';

window.Alpine = Alpine;

Alpine.magic('rupiah', () => formatRupiah);

Alpine.data('board', board);
Alpine.data('refundHistory', refundHistory);

Alpine.start();

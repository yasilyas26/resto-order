// Ringkasan admin: memuat ulang angka, grafik omzet, dan papan dapur tiap beberapa detik.
// Hanya baca; aksi dapur tetap di papan karyawan.
export default (config) => ({
    stats: config.initial.stats,
    orders: config.initial.orders,
    type: 'all',
    offline: false,

    // ---- Grafik omzet (SVG digambar sendiri; satu seri, jadi tanpa legenda) ----
    range: config.initial.stats.range,
    chartW: 800, // diukur dari lebar kotaknya supaya teks tidak ikut melar
    chartH: 280,
    pad: { left: 56, right: 16, top: 16, bottom: 32 },
    hover: null,
    loading: false,

    init() {
        this.measure();
        new ResizeObserver(() => this.measure()).observe(this.$refs.chart);
        setInterval(() => this.refresh(), config.pollMs);
    },

    measure() {
        this.chartW = Math.max(320, Math.round(this.$refs.chart.clientWidth));
    },

    async refresh() {
        try {
            const res = await fetch(`${config.dataUrl}?range=${this.range}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            // Abaikan balasan lama kalau pengguna sudah ganti rentang selagi menunggu.
            if (data.stats.range === this.range) {
                this.stats = data.stats;
                this.orders = data.orders;
            }
            this.offline = false;
        } catch {
            this.offline = true;
        }
    },

    async setRange(range) {
        if (range === this.range) return;
        this.range = range;
        this.hover = null;
        this.loading = true;
        await this.refresh();
        this.loading = false;
    },

    column(kitchen) {
        return this.orders.filter((o) => o.kitchen === kitchen && (this.type === 'all' || o.type === this.type));
    },

    get series() {
        return this.stats.series;
    },

    get unitLabel() {
        return { day: 'per hari', week: 'per minggu', month: 'per bulan' }[this.stats.unit];
    },

    get chartTotal() {
        return this.series.reduce((sum, d) => sum + d.revenue, 0);
    },

    /** Batas atas sumbu Y dibulatkan ke angka "enak" (1, 2, 5 x 10^n) supaya garis bantu rapi. */
    get yMax() {
        const max = Math.max(...this.series.map((d) => d.revenue), 0);
        if (max === 0) return 100000;
        const pow = Math.pow(10, Math.floor(Math.log10(max)));
        const step = [1, 2, 5, 10].find((m) => m * pow >= max / 4) * pow;
        return Math.ceil(max / step) * step;
    },

    get yTicks() {
        return [0, 1, 2, 3, 4].map((i) => (this.yMax / 4) * i);
    },

    y(value) {
        const { top, bottom } = this.pad;
        return top + (this.chartH - top - bottom) * (1 - value / this.yMax);
    },

    get slot() {
        return (this.chartW - this.pad.left - this.pad.right) / this.series.length;
    },

    get barW() {
        return Math.min(this.slot * 0.6, 48);
    },

    /** Titik tengah batang ke-i. */
    cx(i) {
        return this.pad.left + this.slot * (i + 0.5);
    },

    /** Batang dengan ujung atas membulat 4px dan dasar rata di garis nol. */
    barPath(i, value) {
        const x = this.cx(i) - this.barW / 2;
        const w = this.barW;
        const base = this.y(0);
        const top = this.y(value);
        const hgt = base - top;
        if (hgt <= 0) return '';
        const r = Math.min(4, hgt, w / 2);
        return `M${x},${base} V${top + r} Q${x},${top} ${x + r},${top} H${x + w - r} Q${x + w},${top} ${x + w},${top + r} V${base} Z`;
    },

    /**
     * Isi SVG sebagai string untuk x-html. Alpine tidak bisa memakai <template x-for> di dalam <svg>
     * (browser membuat elemen template di namespace SVG tanpa isi), jadi digambar manual di sini.
     */
    get svgMarkup() {
        const p = this.pad;
        const parts = [];

        for (const t of this.yTicks) {
            parts.push(
                `<line x1="${p.left}" x2="${this.chartW - p.right}" y1="${this.y(t)}" y2="${this.y(t)}" stroke="#e5e7eb" stroke-width="1"/>`,
                `<text x="${p.left - 8}" y="${this.y(t) + 4}" text-anchor="end" fill="#4b5563" font-size="11">${this.compact(t)}</text>`,
            );
        }

        // Batang dibuat lebih terang supaya "rel" garis di atasnya yang jadi sorotan.
        this.series.forEach((d, i) => {
            const dim = this.hover !== null && this.hover !== i;
            parts.push(`<path d="${this.barPath(i, d.revenue)}" fill="#f9a99a" opacity="${dim ? 0.5 : 1}"/>`);
        });

        // Garis yang menghubungkan puncak tiap batang: naik kalau omzet naik, turun kalau turun.
        const points = this.series.map((d, i) => `${this.cx(i)},${this.y(d.revenue)}`);
        if (points.length > 1) {
            parts.push(`<polyline points="${points.join(' ')}" fill="none" stroke="#b81f0b" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>`);
        }

        // Titik di tiap puncak hanya kalau tidak sesak; selain itu cukup titik yang disorot.
        const showDots = this.slot >= 14;
        this.series.forEach((d, i) => {
            if (showDots || this.hover === i) {
                const r = this.hover === i ? 5 : 3.5;
                parts.push(`<circle cx="${this.cx(i)}" cy="${this.y(d.revenue)}" r="${r}" fill="#b81f0b" stroke="#fff" stroke-width="2"/>`);
            }
        });

        // Label tanggal: tiap batang kalau muat, selain itu dijarangkan (min. ±54px antar label).
        const step = Math.max(1, Math.ceil(54 / this.slot));
        this.series.forEach((d, i) => {
            if ((this.series.length - 1 - i) % step === 0) {
                parts.push(`<text x="${this.cx(i)}" y="${this.chartH - 10}" text-anchor="middle" fill="#4b5563" font-size="11">${d.label}</text>`);
            }
        });

        // Area sentuh selebar satu slot penuh, lebih besar dari batangnya (paling atas supaya menangkap kursor).
        this.series.forEach((d, i) => {
            parts.push(`<rect data-i="${i}" x="${p.left + this.slot * i}" y="${p.top}" width="${this.slot}" height="${this.chartH - p.top - p.bottom}" fill="transparent"/>`);
        });

        return parts.join('');
    },

    onChartMove(event) {
        const i = event.target.dataset?.i;
        this.hover = i === undefined ? null : Number(i);
    },

    get tooltipLeft() {
        if (this.hover === null) return 0;

        return Math.min(Math.max(this.cx(this.hover), 80), this.chartW - 80);
    },

    compact(n) {
        if (n >= 1e6) return (n / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
        if (n >= 1e3) return Math.round(n / 1e3) + ' rb';
        return String(n);
    },

    rupiah(n) {
        return 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');
    },
});

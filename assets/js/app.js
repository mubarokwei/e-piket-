/**
 * E-Piket - Main JavaScript
 * =========================
 * CATATAN: Semua animasi entri berbasis opacity TIDAK digunakan di sini.
 * Elemen halaman selalu dirender terlihat langsung oleh browser, sehingga
 * tidak ada risiko konten "muncul sekilas lalu hilang" akibat tween GSAP
 * yang tidak selesai (mis. CDN GSAP terblokir di jaringan sekolah).
 */

document.addEventListener('DOMContentLoaded', function() {
    // =============================================
    // Sidebar Toggle
    // =============================================
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileToggle');

    // Create overlay element
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            const isCollapsed = sidebar.classList.toggle('collapsed');
            const icon = this.querySelector('i');
            icon.className = isCollapsed ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
        });
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
    }

    overlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });

    // =============================================
    // Tooltip Init
    // =============================================
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(el) {
        return new bootstrap.Tooltip(el);
    });

    // =============================================
    // Modal: jangan biarkan modal ikut ter-scroll saat dibuka
    // (focus otomatis Bootstrap ke elemen dalam bisa menggeser
    // kontainer modal sehingga judul/header modal terpotong di atas).
    // =============================================
    document.addEventListener('shown.bs.modal', function(e) {
        const m = e.target;
        if (m && typeof m.scrollTop === 'number' && m.scrollTop !== 0) {
            m.scrollTop = 0;
        }
    });

    // =============================================
    // Toast Notifikasi (hasil aksi AJAX)
    // =============================================
    const toastBox = document.createElement('div');
    toastBox.id = 'piket-toast-box';
    document.body.appendChild(toastBox);

    window.showToast = function(type, message) {
        const t = document.createElement('div');
        t.className = 'piket-toast ' + (type === 'danger' ? 'piket-toast-danger' : 'piket-toast-success');
        t.innerHTML = '<i class="bi ' + (type === 'danger' ? 'bi-x-circle-fill' : 'bi-check-circle-fill') + '"></i>' +
            '<span>' + message + '</span>';
        toastBox.appendChild(t);
        setTimeout(function() {
            t.classList.add('hide');
            setTimeout(function() { t.remove(); }, 400);
        }, 3500);
    };

    // =============================================
    // AJAX: muat ulang area data (#ajax-area) tanpa refresh
    // =============================================
    window.piketAjax = {
        refreshArea: function() {
            return fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.text(); })
                .then(function(html) { piketAjax.swapArea(html); });
        },
        swapArea: function(html) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const fresh = doc.getElementById('ajax-area');
            const current = document.getElementById('ajax-area');
            if (fresh && current) {
                current.innerHTML = fresh.innerHTML;
            }
        },
        post: function(form) {
            const btn = form.querySelector('[type="submit"]');
            if (btn) btn.disabled = true;
            const fd = new FormData(form);
            // Catatan: jangan pakai form.action — properti itu bisa ditimpa oleh
            // kontrol form bernama "action" (hidden input action=add/edit/delete).
            const url = form.getAttribute('action') || window.location.href;
            return fetch(url, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                showToast(d.success ? 'success' : 'danger', d.message || (d.success ? 'Berhasil disimpan!' : 'Terjadi kesalahan.'));
                if (d.success) {
                    form.reset();
                    const modal = form.closest('.modal');
                    if (modal) {
                        const inst = bootstrap.Modal.getInstance(modal);
                        if (inst) inst.hide();
                    }
                    // tutup dropdown jika form hapus ada di dalamnya
                    const dd = form.closest('.dropdown-menu');
                    if (dd && dd.parentElement) {
                        // Biarkan Bootstrap menutup dropdown otomatis
                    }
                    return piketAjax.refreshArea();
                }
            })
            .catch(function() { showToast('danger', 'Koneksi gagal. Coba lagi.'); })
            .finally(function() { if (btn) btn.disabled = false; });
        },
        filter: function(form) {
            const params = new URLSearchParams(new FormData(form));
            const url = window.location.pathname + '?' + params.toString();
            return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.text(); })
                .then(function(html) { piketAjax.swapArea(html); });
        }
    };

    // =============================================
    // Delegasi event (tetap berfungsi setelah swap AJAX)
    // =============================================
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.matches && form.matches('form.ajax-form')) {
            e.preventDefault();
            if (form.hasAttribute('data-confirm') && !confirm(form.getAttribute('data-confirm'))) {
                return;
            }
            piketAjax.post(form);
        } else if (form.matches && form.matches('form.ajax-filter')) {
            e.preventDefault();
            piketAjax.filter(form);
        }
    });

    document.addEventListener('change', function(e) {
        const form = e.target.closest && e.target.closest('form.ajax-filter');
        if (form) {
            e.preventDefault();
            piketAjax.filter(form);
        }
    });

    // Pencarian live: filter otomatis saat mengetik (debounce 350ms)
    let searchTimer = null;
    document.addEventListener('input', function(e) {
        const input = e.target;
        if (input.matches && input.matches('form.ajax-filter input[type="text"], form.ajax-filter input[type="search"]')) {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                piketAjax.filter(input.form);
            }, 350);
        }
    });

    document.addEventListener('click', function(e) {
        const el = e.target.closest && e.target.closest('[data-confirm]');
        if (el && !el.matches('form.ajax-form, form.ajax-form *')) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
                e.stopPropagation();
            }
        }
    });

    // =============================================
    // Filter hari per kartu kelas (kelas.php): sembunyikan/tampilkan baris
    // =============================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest && e.target.closest('button.pengajar-filter[data-filter-day]');
        if (!btn) return;
        const box = btn.closest('.pengajar-box');
        if (!box) return;
        const day = btn.getAttribute('data-filter-day');
        box.querySelectorAll('.pengajar-filter').forEach(function(p) {
            p.classList.toggle('active', p === btn);
        });
        let visible = 0;
        box.querySelectorAll('tr.pengajar-row').forEach(function(tr) {
            const show = !day || tr.getAttribute('data-hari') === day;
            tr.classList.toggle('d-none', !show);
            if (show) visible++;
        });
        const empty = box.querySelector('[data-empty-filter]');
        if (empty) empty.classList.toggle('d-none', visible > 0);
        const count = box.querySelector('.pengajar-count');
        if (count) count.textContent = visible + ' Jadwal';
    });

    // =============================================
    // Tab hari (jadwal.php): klik -> ganti filter hari via AJAX
    // =============================================
    document.addEventListener('click', function(e) {
        const tab = e.target.closest && e.target.closest('[data-hari-tab]');
        if (!tab) return;
        e.preventDefault();
        const val = tab.getAttribute('data-hari-tab');
        const form = document.querySelector('#ajax-area form.ajax-filter');
        if (!form) return;
        const sel = form.querySelector('select[name="hari"]');
        if (sel) sel.value = val;
        // aktifkan pill yang diklik, nonaktifkan lainnya
        document.querySelectorAll('[data-hari-tab]').forEach(function(p) {
            p.classList.toggle('active', p === tab);
        });
        piketAjax.filter(form);
    });

    // =============================================
    // Auto-hide alerts after 5 seconds
    // =============================================
    document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });

    // =============================================
    // Print function
    // =============================================
    window.printReport = function() {
        window.print();
    };

    // =============================================
    // Export table to CSV
    // =============================================
    window.exportCSV = function(tableId, filename) {
        const table = document.getElementById(tableId);
        if (!table) return;

        let csv = [];
        const rows = table.querySelectorAll('tr');

        rows.forEach(function(row) {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            cols.forEach(function(col) {
                rowData.push('"' + col.textContent.replace(/"/g, '""').trim() + '"');
            });
            csv.push(rowData.join(','));
        });

        const csvContent = csv.join('\n');
        const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename + '.csv';
        link.click();
    };
});
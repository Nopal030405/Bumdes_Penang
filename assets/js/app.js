/**
 * Core Javascript Logic - BUMDes Penang
 * Mengelola navigasi SPA, timer real-time, validasi tanggal kalender, dan AJAX API.
 */

document.addEventListener('DOMContentLoaded', () => {
    // --- State Global ---
    let config = {
        operasional_mulai: '07:00',
        operasional_selesai: '17:00',
        status_libur: '0',
        status_tutup_sementara: '0'
    };
    let activeFilter = 'all';
    let todayTransactions = [];
    let calendarYear = new Date().getFullYear();
    let calendarMonth = new Date().getMonth(); // 0-11
    let calendarSelectedDate = ''; // YYYY-MM-DD
    
    // --- Elemen DOM Navigasi Halaman ---
    const pages = {
        menu: document.getElementById('page-menu'),
        harian: document.getElementById('page-harian'),
        mingguan: document.getElementById('page-mingguan'),
        tarif: document.getElementById('page-tarif'),
        rekapan_transaksi: document.getElementById('page-rekapan-transaksi')
    };
    
    // --- Elemen DOM Lainnya ---
    const clockEl = document.getElementById('realtime-clock');
    const statusTextEl = document.getElementById('status-text');
    const statusIndicatorEl = document.getElementById('status-indicator');
    
    const quickInputContainer = document.getElementById('quick-input-container');
    const statsContainer = document.getElementById('stats-container');
    const grandTotalEl = document.getElementById('grand-total');
    
    const formTambahan = document.getElementById('form-tambahan');
    const listTambahan = document.getElementById('list-tambahan');
    const inputTanggalEkspor = document.getElementById('tanggal-ekspor');
    const btnEksporHarian = document.getElementById('btn-ekspor-harian');
    
    // Elemen Kalender Page 2b
    const btnPrevMonth = document.getElementById('btn-prev-month');
    const btnNextMonth = document.getElementById('btn-next-month');
    const btnToggleLiburSelected = document.getElementById('btn-toggle-libur-selected');
    const btnExportDailySelected = document.getElementById('btn-export-daily-selected');
    const btnExportRange = document.getElementById('btn-export-range');
    
    // Elemen Halaman Tarif
    const formSettingOperasional = document.getElementById('form-setting-operasional');
    const formTambahTarif = document.getElementById('form-tambah-tarif');
    const tableBodyTarif = document.getElementById('table-body-tarif');

    // --- Helper Formatter ---
    const formatRupiah = (number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(number);
    };

    const showToast = (message, type = 'success') => {
        const container = document.getElementById('toast-container');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        let icon = 'ℹ️';
        if (type === 'success') icon = '✅';
        if (type === 'error') icon = '❌';
        
        toast.innerHTML = `<span>${icon}</span> <div>${message}</div>`;
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    };

    // --- Sistem Navigasi SPA ---
    const showPage = (pageId) => {
        Object.keys(pages).forEach(key => {
            if (pages[key]) {
                pages[key].classList.remove('active-page');
            }
        });
        
        if (pages[pageId]) {
            pages[pageId].classList.add('active-page');
        }
        
        // Memuat ulang data setiap kali masuk halaman tertentu
        if (pageId === 'harian' || pageId === 'tarif' || pageId === 'menu' || pageId === 'rekapan_transaksi' || pageId === 'mingguan') {
            loadAppConfigAndStats();
        }
        if (pageId === 'harian') {
            loadPendapatanTambahan();
        }
        if (pageId === 'rekapan_transaksi') {
            loadRekapanTransaksiList();
        }
        if (pageId === 'mingguan') {
            renderCalendar();
            resetCalendarSelection();
        }
    };

    // Binding Click Event Card Navigasi
    document.getElementById('card-goto-harian').addEventListener('click', () => showPage('harian'));
    document.getElementById('card-goto-mingguan').addEventListener('click', () => showPage('mingguan'));
    document.getElementById('card-goto-tarif').addEventListener('click', () => showPage('tarif'));
    
    // Binding Tombol Kembali
    document.querySelectorAll('.btn-back-menu').forEach(btn => {
        btn.addEventListener('click', () => showPage('menu'));
    });

    // --- Jam Real-time & Status Operasional ---
    const updateTimeAndStatus = () => {
        const sekarang = new Date();
        const jam = String(sekarang.getHours()).padStart(2, '0');
        const menit = String(sekarang.getMinutes()).padStart(2, '0');
        const detik = String(sekarang.getSeconds()).padStart(2, '0');
        const waktuStr = `${jam}:${menit}:${detik}`;
        
        if (clockEl) {
            clockEl.textContent = waktuStr;
        }
        
        const jamMenitStr = `${jam}:${menit}`;
        const isLibur = (config.status_libur === '1');
        const isTutupSementara = (config.status_tutup_sementara === '1');
        const isBuka = !isLibur && !isTutupSementara && (jamMenitStr >= config.operasional_mulai && jamMenitStr <= config.operasional_selesai);
        
        // 1. Update Indikator Status Header Utama
        if (isLibur) {
            statusTextEl.textContent = 'TUTUP (HARI LIBUR)';
            statusIndicatorEl.className = 'status-indicator tutup';
            togglePencatatanButtons(false);
        } else if (isTutupSementara) {
            statusTextEl.textContent = 'TUTUP SEMENTARA';
            statusIndicatorEl.className = 'status-indicator tutup';
            togglePencatatanButtons(false);
        } else if (isBuka) {
            statusTextEl.textContent = 'BUKA (OPERASIONAL AKTIF)';
            statusIndicatorEl.className = 'status-indicator buka';
            togglePencatatanButtons(true);
        } else {
            statusTextEl.textContent = 'TUTUP (DI LUAR JAM KERJA)';
            statusIndicatorEl.className = 'status-indicator tutup';
            togglePencatatanButtons(false);
        }
        
        // 2. Update Status & Tombol Libur Satu Hari Utuh di Page 1 (Menu Utama)
        const labelStatusLiburMenu = document.getElementById('label-status-libur-menu');
        const btnToggleLiburMenu = document.getElementById('btn-toggle-libur-menu');
        if (labelStatusLiburMenu && btnToggleLiburMenu) {
            if (isLibur) {
                labelStatusLiburMenu.textContent = '🏖️ LIBUR UTUH';
                labelStatusLiburMenu.className = 'badge badge-purple';
                btnToggleLiburMenu.textContent = '💼 Aktifkan Operasional';
                btnToggleLiburMenu.className = 'btn btn-success';
            } else {
                labelStatusLiburMenu.textContent = '🟢 AKTIF';
                labelStatusLiburMenu.className = 'badge badge-blue';
                btnToggleLiburMenu.textContent = '🏖️ Atur Hari Libur';
                btnToggleLiburMenu.className = 'btn btn-warning';
            }
        }
        
        // 3. Update Tombol Tutup Sementara di Page 2a (Rekap Harian)
        const btnToggleTutupSementara = document.getElementById('btn-toggle-tutup-sementara');
        if (btnToggleTutupSementara) {
            if (isLibur) {
                btnToggleTutupSementara.textContent = '⚠️ Tutup Sementara';
                btnToggleTutupSementara.className = 'btn btn-secondary disabled';
                btnToggleTutupSementara.setAttribute('disabled', 'true');
            } else {
                btnToggleTutupSementara.removeAttribute('disabled');
                if (isTutupSementara) {
                    btnToggleTutupSementara.textContent = '💼 Buka Kembali Operasional';
                    btnToggleTutupSementara.className = 'btn btn-success';
                } else {
                    btnToggleTutupSementara.textContent = '⚠️ Tutup Sementara / Lebih Awal';
                    btnToggleTutupSementara.className = 'btn btn-warning';
                }
            }
        }
    };

    const togglePencatatanButtons = (enabled) => {
        document.querySelectorAll('.btn-quick-parking').forEach(btn => {
            if (enabled) {
                btn.classList.remove('disabled');
                btn.removeAttribute('disabled');
            } else {
                btn.classList.add('disabled');
                btn.setAttribute('disabled', 'true');
            }
        });
    };

    setInterval(updateTimeAndStatus, 1000);

    // --- Render Elemen Dinamis Harian ---
    const renderQuickButtons = (kendaraanData) => {
        if (!quickInputContainer) return;
        quickInputContainer.innerHTML = '';
        
        const emojiMap = {
            motor: '🏍️',
            mobil: '🚗',
            truk: '🚛',
            bus: '🚌',
            sepeda: '🚲',
            default: '🚗'
        };
        
        const keys = Object.keys(kendaraanData);
        if (keys.length === 0) {
            quickInputContainer.innerHTML = '<div class="text-center text-muted" style="grid-column: 1 / span 2;">Belum ada jenis kendaraan terdaftar.</div>';
            return;
        }
        
        keys.forEach(jenis => {
            const item = kendaraanData[jenis];
            const emoji = emojiMap[jenis] || emojiMap.default;
            
            const button = document.createElement('button');
            button.className = 'btn-quick btn-quick-parking';
            
            if (jenis === 'motor') {
                button.classList.add('btn-motor');
            } else if (jenis === 'mobil') {
                button.classList.add('btn-mobil');
            } else {
                // Style custom neobrutalist
                button.style.backgroundColor = '#E5DBFF';
            }
            
            button.innerHTML = `
                <span class="btn-icon">${emoji}</span>
                <span class="btn-label">${jenis.toUpperCase()}</span>
                <span class="btn-tarif">${formatRupiah(item.tarif)}</span>
            `;
            
            button.addEventListener('click', () => {
                if (button.classList.contains('disabled')) {
                    showToast('Tombol terkunci. Di luar jam operasional, sedang libur, atau tutup sementara!', 'error');
                    return;
                }
                
                button.style.transform = 'scale(0.95)';
                setTimeout(() => button.style.transform = '', 100);
                
                postTransaksi(jenis);
            });
            
            quickInputContainer.appendChild(button);
        });
    };

    const renderStats = (data) => {
        if (!statsContainer) return;
        statsContainer.innerHTML = '';
        
        if (grandTotalEl) {
            grandTotalEl.textContent = formatRupiah(data.grand_total);
        }
        
        const keys = Object.keys(data.kendaraan);
        keys.forEach(jenis => {
            const item = data.kendaraan[jenis];
            
            const div = document.createElement('div');
            div.className = 'stat-item';
            div.style.cursor = 'pointer';
            div.innerHTML = `
                <div class="stat-label">Parkir ${jenis.toUpperCase()}</div>
                <div class="stat-value" style="margin: 0.5rem 0;">${formatRupiah(item.total)}</div>
                <div class="stat-counter">${item.jumlah} kendaraan</div>
            `;
            
            div.addEventListener('click', () => {
                activeFilter = jenis;
                updateFilterButtons();
                showPage('rekapan_transaksi');
            });
            
            statsContainer.appendChild(div);
        });
    };

    // --- Pemuatan Data Utama via API ---
    const loadAppConfigAndStats = () => {
        fetch('api/pengaturan.php')
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    config.operasional_mulai = res.data.operasional_mulai;
                    config.operasional_selesai = res.data.operasional_selesai;
                    config.status_libur = res.data.status_libur;
                    config.status_tutup_sementara = res.data.status_tutup_sementara;
                    
                    const textOperasional = document.getElementById('info-jam-operasional');
                    if (textOperasional) {
                        textOperasional.textContent = `Aktif: ${config.operasional_mulai} s.d. ${config.operasional_selesai}`;
                    }
                    
                    // Render list tarif di Halaman 2c
                    renderTarifList(res.data);
                    
                    return fetch('api/transaksi.php');
                }
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    renderQuickButtons(res.data.kendaraan);
                    renderStats(res.data);
                    updateTimeAndStatus();
                }
            })
            .catch(err => {
                console.error('Gagal mengambil konfigurasi dan statistik:', err);
                showToast('Gagal memuat statistik harian dari server.', 'error');
            });
    };

    // --- Kirim Transaksi Parkir ---
    const postTransaksi = (jenis) => {
        fetch('api/transaksi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ jenis_kendaraan: jenis })
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                showToast(res.message, 'success');
                loadAppConfigAndStats();
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Koneksi terputus. Gagal menyimpan data.', 'error');
        });
    };

    // --- Pendapatan Tambahan Harian ---
    if (formTambahan) {
        formTambahan.addEventListener('submit', (e) => {
            e.preventDefault();
            const namaInput = document.getElementById('nama-tambahan');
            const nominalInput = document.getElementById('nominal-tambahan');
            
            const nama = namaInput.value.trim();
            const nominal = parseInt(nominalInput.value);
            
            if (!nama) {
                showToast('Masukkan nama keperluan pendapatan tambahan!', 'error');
                return;
            }
            if (isNaN(nominal) || nominal <= 0) {
                showToast('Masukkan nominal yang valid!', 'error');
                return;
            }
            
            fetch('api/pendapatan_tambahan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nama_item: nama, nominal: nominal })
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    namaInput.value = '';
                    nominalInput.value = '';
                    loadAppConfigAndStats();
                    loadPendapatanTambahan();
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Gagal mencatat pendapatan tambahan.', 'error');
            });
        });
    }

    const loadPendapatanTambahan = () => {
        if (!listTambahan) return;
        fetch('api/pendapatan_tambahan.php')
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    listTambahan.innerHTML = '';
                    if (res.data.length === 0) {
                        listTambahan.innerHTML = '<div class="text-muted text-center" style="font-size: 0.85rem; padding: 1rem 0;">Belum ada pendapatan tambahan hari ini.</div>';
                        return;
                    }
                    
                    res.data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'history-item';
                        div.style.display = 'flex';
                        div.style.justifyContent = 'space-between';
                        div.style.alignItems = 'center';
                        div.innerHTML = `
                            <div class="history-details" style="flex-grow: 1;">
                                <span class="history-name">${item.nama_item}</span>
                                <span class="history-time">Jam ${item.waktu}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="history-amount">+${formatRupiah(item.nominal)}</span>
                                <button class="btn-delete-tambahan" data-id="${item.id}" style="background: var(--accent-orange); color: black; border: var(--border-thin); font-weight: 800; cursor: pointer; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 4px; box-shadow: 2px 2px 0px #000000; font-size: 0.75rem;">X</button>
                            </div>
                        `;
                        
                        div.querySelector('.btn-delete-tambahan').addEventListener('click', (e) => {
                            e.stopPropagation();
                            const id = e.target.getAttribute('data-id');
                            if (confirm('Apakah Anda yakin ingin menghapus pendapatan tambahan ini?')) {
                                deletePendapatanTambahan(id);
                            }
                        });
                        
                        listTambahan.appendChild(div);
                    });
                }
            })
            .catch(err => console.error('Gagal memuat pendapatan tambahan:', err));
    };

    // --- Toggle Libur Satu Hari Utuh (Page 1) ---
    const btnToggleLiburMenu = document.getElementById('btn-toggle-libur-menu');
    if (btnToggleLiburMenu) {
        btnToggleLiburMenu.addEventListener('click', () => {
            const nextStatus = (config.status_libur === '1') ? '0' : '1';
            
            fetch('api/pengaturan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'toggle_libur',
                    status_libur: nextStatus
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    loadAppConfigAndStats();
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Gagal mengubah status hari libur.', 'error');
            });
        });
    }

    // --- Toggle Tutup Sementara / Lebih Awal (Page 2a) ---
    const btnToggleTutupSementara = document.getElementById('btn-toggle-tutup-sementara');
    if (btnToggleTutupSementara) {
        btnToggleTutupSementara.addEventListener('click', () => {
            if (config.status_libur === '1') {
                showToast('Gagal: Hari ini sedang diset Libur Satu Hari Utuh!', 'error');
                return;
            }
            
            const nextStatus = (config.status_tutup_sementara === '1') ? '0' : '1';
            
            fetch('api/pengaturan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'toggle_tutup_sementara',
                    status_tutup_sementara: nextStatus
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    loadAppConfigAndStats();
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Gagal merubah status operasional.', 'error');
            });
        });
    }

    // --- Ekspor Rekap Harian ---
    if (btnEksporHarian) {
        btnEksporHarian.addEventListener('click', () => {
            const tanggal = inputTanggalEkspor.value;
            if (!tanggal) {
                showToast('Pilih tanggal laporan harian terlebih dahulu!', 'error');
                return;
            }
            window.location.href = `api/ekspor.php?tanggal=${tanggal}`;
            showToast('Memproses berkas rekap harian...', 'info');
        });
    }

    // --- modul 2b: Kalender Laporan Keuangan Interaktif ---
    const renderCalendar = () => {
        const monthYearEl = document.getElementById('calendar-month-year');
        const gridEl = document.getElementById('calendar-days-grid');
        if (!monthYearEl || !gridEl) return;
        
        gridEl.innerHTML = '<div class="text-center text-muted" style="grid-column: 1 / span 7; padding: 2rem;">Memuat status kalender...</div>';
        
        const bulans = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        monthYearEl.textContent = `${bulans[calendarMonth]} ${calendarYear}`;
        
        fetch(`api/transaksi.php?action=month_status&year=${calendarYear}&month=${calendarMonth + 1}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    generateCalendarCells(res.data);
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Gagal memuat status bulan ini.', 'error');
            });
    };

    const generateCalendarCells = (statusData) => {
        const gridEl = document.getElementById('calendar-days-grid');
        if (!gridEl) return;
        gridEl.innerHTML = '';
        
        const firstDayDate = new Date(calendarYear, calendarMonth, 1);
        let firstDayIndex = firstDayDate.getDay(); // 0 = Minggu
        firstDayIndex = (firstDayIndex === 0) ? 6 : firstDayIndex - 1; // Senin = 0
        
        const numDays = new Date(calendarYear, calendarMonth + 1, 0).getDate();
        
        for (let i = 0; i < firstDayIndex; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'calendar-day-cell empty-cell';
            gridEl.appendChild(emptyCell);
        }
        
        for (let day = 1; day <= numDays; day++) {
            const formattedMonth = String(calendarMonth + 1).padStart(2, '0');
            const formattedDay = String(day).padStart(2, '0');
            const dateStr = `${calendarYear}-${formattedMonth}-${formattedDay}`;
            
            const cell = document.createElement('div');
            cell.className = 'calendar-day-cell';
            cell.setAttribute('data-date', dateStr);
            
            const isLibur = statusData.libur.includes(dateStr);
            const isTerekap = statusData.terekap.includes(dateStr);
            
            if (isLibur) {
                cell.classList.add('libur');
            } else if (isTerekap) {
                cell.classList.add('terekap');
            }
            
            if (calendarSelectedDate === dateStr) {
                cell.classList.add('active-day');
            }
            
            cell.innerHTML = `<span class="day-number">${day}</span>`;
            
            cell.addEventListener('click', () => {
                document.querySelectorAll('.calendar-day-cell.active-day').forEach(el => {
                    el.classList.remove('active-day');
                });
                
                cell.classList.add('active-day');
                calendarSelectedDate = dateStr;
                
                loadSelectedDateStats(dateStr);
            });
            
            gridEl.appendChild(cell);
        }
    };

    const resetCalendarSelection = () => {
        calendarSelectedDate = '';
        const placeholder = document.getElementById('calendar-selection-placeholder');
        const details = document.getElementById('calendar-selection-details');
        if (placeholder) placeholder.style.display = 'block';
        if (details) details.style.display = 'none';
    };

    const loadSelectedDateStats = (date) => {
        const placeholder = document.getElementById('calendar-selection-placeholder');
        const details = document.getElementById('calendar-selection-details');
        if (placeholder) placeholder.style.display = 'none';
        if (details) details.style.display = 'block';
        
        const titleEl = document.getElementById('selected-date-title');
        const grandTotalEl = document.getElementById('selected-date-grand-total');
        const statsGrid = document.getElementById('selected-date-stats-grid');
        const rangeStartDate = document.getElementById('range-start-date');
        const rangeEndDate = document.getElementById('range-end-date');
        
        if (titleEl) {
            const parts = date.split('-');
            titleEl.textContent = `Rekap Tanggal: ${parts[2]}-${parts[1]}-${parts[0]}`;
        }
        
        if (rangeStartDate) rangeStartDate.value = date;
        if (rangeEndDate) {
            const selDateObj = new Date(date);
            selDateObj.setDate(selDateObj.getDate() + 6);
            const todayStr = new Date().toISOString().split('T')[0];
            const endStr = selDateObj.toISOString().split('T')[0];
            rangeEndDate.value = (endStr > todayStr) ? todayStr : endStr;
        }
        
        fetch(`api/transaksi.php?tanggal=${date}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    const data = res.data;
                    
                    if (grandTotalEl) grandTotalEl.textContent = formatRupiah(data.grand_total);
                    
                    if (btnToggleLiburSelected) {
                        btnToggleLiburSelected.setAttribute('data-libur', data.is_libur ? '1' : '0');
                        if (data.is_libur) {
                            btnToggleLiburSelected.textContent = '🏖️ LIBUR';
                            btnToggleLiburSelected.className = 'badge badge-purple';
                        } else {
                            btnToggleLiburSelected.textContent = '🟢 AKTIF';
                            btnToggleLiburSelected.className = 'badge badge-blue';
                        }
                    }
                    
                    if (statsGrid) {
                        statsGrid.innerHTML = '';
                        const keys = Object.keys(data.kendaraan);
                        keys.forEach(jenis => {
                            const item = data.kendaraan[jenis];
                            const itemDiv = document.createElement('div');
                            itemDiv.style.background = '#FFFFFF';
                            itemDiv.style.border = 'var(--border-thin)';
                            itemDiv.style.borderRadius = '6px';
                            itemDiv.style.padding = '0.75rem';
                            itemDiv.style.textAlign = 'center';
                            itemDiv.style.boxShadow = '2px 2px 0px #000000';
                            itemDiv.innerHTML = `
                                <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted);">${jenis}</div>
                                <div style="font-size: 1.15rem; font-weight: 900; margin: 0.25rem 0;">${formatRupiah(item.total)}</div>
                                <div style="font-size: 0.7rem; font-weight: 700; background: var(--accent-teal); border: var(--border-thin); border-radius: 4px; display: inline-block; padding: 0.05rem 0.35rem; box-shadow: 1px 1px 0px #000000;">${item.jumlah} unit</div>
                            `;
                            statsGrid.appendChild(itemDiv);
                        });
                        
                        const tambahanDiv = document.createElement('div');
                        tambahanDiv.style.background = '#FFFFFF';
                        tambahanDiv.style.border = 'var(--border-thin)';
                        tambahanDiv.style.borderRadius = '6px';
                        tambahanDiv.style.padding = '0.75rem';
                        tambahanDiv.style.textAlign = 'center';
                        tambahanDiv.style.boxShadow = '2px 2px 0px #000000';
                        tambahanDiv.innerHTML = `
                            <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted);">Tambahan</div>
                            <div style="font-size: 1.15rem; font-weight: 900; margin: 0.25rem 0;">${formatRupiah(data.tambahan.total)}</div>
                            <div style="font-size: 0.7rem; font-weight: 700; background: var(--accent-teal); border: var(--border-thin); border-radius: 4px; display: inline-block; padding: 0.05rem 0.35rem; box-shadow: 1px 1px 0px #000000;">Manual</div>
                        `;
                        statsGrid.appendChild(tambahanDiv);
                    }
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Gagal memuat rincian statistik.', 'error');
            });
    };

    const toggleLiburForSelectedDate = () => {
        if (!btnToggleLiburSelected || !calendarSelectedDate) return;
        
        const currentStatus = btnToggleLiburSelected.getAttribute('data-libur') === '1';
        const nextStatus = currentStatus ? '0' : '1';
        
        fetch('api/pengaturan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'toggle_libur_date',
                tanggal: calendarSelectedDate,
                status_libur: nextStatus
            })
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                showToast(res.message, 'success');
                renderCalendar();
                loadSelectedDateStats(calendarSelectedDate);
                loadAppConfigAndStats();
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Gagal memperbarui status libur.', 'error');
        });
    };

    // Binding Click Events Kalender Page 2b
    if (btnPrevMonth) {
        btnPrevMonth.addEventListener('click', () => {
            calendarMonth--;
            if (calendarMonth < 0) {
                calendarMonth = 11;
                calendarYear--;
            }
            renderCalendar();
            resetCalendarSelection();
        });
    }
    if (btnNextMonth) {
        btnNextMonth.addEventListener('click', () => {
            calendarMonth++;
            if (calendarMonth > 11) {
                calendarMonth = 0;
                calendarYear++;
            }
            renderCalendar();
            resetCalendarSelection();
        });
    }
    if (btnToggleLiburSelected) {
        btnToggleLiburSelected.addEventListener('click', toggleLiburForSelectedDate);
    }
    if (btnExportDailySelected) {
        btnExportDailySelected.addEventListener('click', () => {
            if (!calendarSelectedDate) return;
            window.location.href = `api/ekspor.php?tanggal=${calendarSelectedDate}`;
            showToast('Memproses rekap harian...', 'info');
        });
    }
    if (btnExportRange) {
        btnExportRange.addEventListener('click', () => {
            const start = document.getElementById('range-start-date').value;
            const end = document.getElementById('range-end-date').value;
            if (!start || !end) {
                showToast('Harap tentukan tanggal mulai dan selesai!', 'error');
                return;
            }
            window.location.href = `api/ekspor_jangkauan.php?start_date=${start}&end_date=${end}`;
            showToast('Memproses rekap jangkauan custom...', 'info');
        });
    }

    // --- modul 2c: pengaturan tarif dinamis ---
    const renderTarifList = (settings) => {
        if (!tableBodyTarif) return;
        tableBodyTarif.innerHTML = '';
        
        const keys = Object.keys(settings).filter(k => k.startsWith('tarif_'));
        
        if (keys.length === 0) {
            tableBodyTarif.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Belum ada tarif terdaftar.</td></tr>';
            return;
        }
        
        keys.forEach(key => {
            const jenis = key.replace('tarif_', '');
            const tarif = parseInt(settings[key]);
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="text-transform: capitalize; font-weight: 700;">${jenis}</td>
                <td>${formatRupiah(tarif)}</td>
                <td style="text-align: center;">
                    <button class="action-icon-btn btn-delete-tarif" data-jenis="${jenis}">Hapus</button>
                </td>
            `;
            
            tr.querySelector('.btn-delete-tarif').addEventListener('click', (e) => {
                const jns = e.target.getAttribute('data-jenis');
                if (confirm(`Apakah Anda yakin ingin menghapus jenis kendaraan "${jns}"?`)) {
                    deleteTarif(jns);
                }
            });
            
            tableBodyTarif.appendChild(tr);
        });
    };

    const deleteTarif = (jenis) => {
        fetch('api/pengaturan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_tarif', jenis_kendaraan: jenis })
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                showToast(res.message, 'success');
                loadAppConfigAndStats();
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Gagal menghapus jenis kendaraan.', 'error');
        });
    };

    if (formTambahTarif) {
        formTambahTarif.addEventListener('submit', (e) => {
            e.preventDefault();
            const namaInput = document.getElementById('input-nama-kendaraan');
            const tarifInput = document.getElementById('input-tarif-kendaraan');
            
            const nama = namaInput.value.trim();
            const tarif = parseInt(tarifInput.value);
            
            if (!nama) {
                showToast('Nama kendaraan tidak boleh kosong!', 'error');
                return;
            }
            if (isNaN(tarif) || tarif < 0) {
                showToast('Tarif parkir tidak boleh negatif!', 'error');
                return;
            }
            
            fetch('api/pengaturan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_tarif',
                    jenis_kendaraan: nama,
                    tarif: tarif
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    namaInput.value = '';
                    tarifInput.value = '';
                    loadAppConfigAndStats();
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Gagal mendaftarkan tarif baru.', 'error');
            });
        });
    }

    if (formSettingOperasional) {
        formSettingOperasional.addEventListener('submit', (e) => {
            e.preventDefault();
            const mulai = document.getElementById('input-operasional-mulai').value.trim();
            const selesai = document.getElementById('input-operasional-selesai').value.trim();
            
            fetch('api/pengaturan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_general',
                    operasional_mulai: mulai,
                    operasional_selesai: selesai
                })
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    showToast(res.message, 'success');
                    loadAppConfigAndStats();
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Gagal memperbarui jam operasional.', 'error');
            });
        });
    }

    // --- Pendapatan Tambahan Delete ---
    const deletePendapatanTambahan = (id) => {
        fetch('api/pendapatan_tambahan.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                showToast(res.message, 'success');
                loadAppConfigAndStats();
                loadPendapatanTambahan();
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Gagal menghapus pendapatan tambahan.', 'error');
        });
    };

    // --- Page 3a: Rekapan Transaksi Harian (Mobil & Motor) ---
    const loadRekapanTransaksiList = () => {
        const tbody = document.getElementById('table-body-rekapan-transaksi');
        if (!tbody) return;
        
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted" style="text-align: center; padding: 2rem;">Memuat rekapan transaksi...</td></tr>';
        
        fetch('api/transaksi.php?action=list_today')
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    todayTransactions = res.data;
                    renderRekapanTransaksiTable();
                } else {
                    showToast(res.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Gagal memuat rekapan transaksi.', 'error');
            });
    };

    const renderRekapanTransaksiTable = () => {
        const tbody = document.getElementById('table-body-rekapan-transaksi');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        const filtered = todayTransactions.filter(item => {
            if (activeFilter === 'all') return true;
            return item.jenis_kendaraan === activeFilter;
        });
        
        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted" style="text-align: center; padding: 2rem;">Tidak ada transaksi ${activeFilter !== 'all' ? activeFilter : ''} hari ini.</td></tr>`;
            return;
        }
        
        filtered.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>Jam ${item.waktu}</td>
                <td style="text-transform: capitalize; font-weight: 700;">${item.jenis_kendaraan}</td>
                <td>${formatRupiah(item.tarif)}</td>
                <td style="text-align: center;">
                    <button class="btn-delete-transaksi" data-id="${item.id}" style="background: var(--accent-orange); color: black; border: var(--border-thin); font-weight: 800; cursor: pointer; padding: 0.3rem 0.6rem; border-radius: 4px; box-shadow: 2px 2px 0px #000000; font-size: 0.8rem; font-family: var(--font-heading);">Hapus</button>
                </td>
            `;
            
            tr.querySelector('.btn-delete-transaksi').addEventListener('click', (e) => {
                const id = e.target.getAttribute('data-id');
                if (confirm('Apakah Anda yakin ingin menghapus transaksi ini?')) {
                    deleteTransaksi(id);
                }
            });
            
            tbody.appendChild(tr);
        });
    };

    const deleteTransaksi = (id) => {
        fetch('api/transaksi.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                showToast(res.message, 'success');
                loadAppConfigAndStats();
                loadRekapanTransaksiList();
            } else {
                showToast(res.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Gagal menghapus transaksi.', 'error');
        });
    };

    // Binding Tombol Kembali Page 3a
    const btnBackHarian = document.querySelector('.btn-back-harian');
    if (btnBackHarian) {
        btnBackHarian.addEventListener('click', () => showPage('harian'));
    }

    // Binding Filter Page 3a
    document.querySelectorAll('.btn-filter').forEach(btn => {
        btn.addEventListener('click', (e) => {
            activeFilter = e.target.getAttribute('data-filter');
            updateFilterButtons();
            renderRekapanTransaksiTable();
        });
    });

    const updateFilterButtons = () => {
        document.querySelectorAll('.btn-filter').forEach(btn => {
            if (btn.getAttribute('data-filter') === activeFilter) {
                btn.classList.add('active');
                btn.style.background = 'var(--accent-teal)';
            } else {
                btn.classList.remove('active');
                btn.style.background = 'white';
            }
        });
    };

    // --- Inisialisasi Default Halaman ---
    const initDefaults = () => {
        const todayStr = new Date().toISOString().split('T')[0];
        if (inputTanggalEkspor) {
            inputTanggalEkspor.value = todayStr;
        }
        
        loadAppConfigAndStats();
    };

    initDefaults();
});

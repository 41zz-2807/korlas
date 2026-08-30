@extends('layouts.public')

@section('content')
<div class="header-wrap">
    <div class="container">
        <div class="header-top">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 class="mb-1 fw-bold school-name">{{ config('app.school_name') }}</h3>
                    <div class="school-subtitle">{{ config('app.school_subtitle') }}</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($isAdmin ?? false)
                        <span class="btn btn-sm btn-warning text-dark"><i class="bi bi-shield-lock"></i> Admin</span>
                        <a href="{{ route('admin.transactions.create') }}" class="btn btn-sm btn-light">
                            <i class="bi bi-plus-circle"></i> Input
                        </a>
                        <a href="{{ route('admin.backup') }}" class="btn btn-sm btn-light">
                            <i class="bi bi-database"></i> Backup
                        </a>
                        <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-light">Keluar</button>
                        </form>
                    @else
                        <span class="btn btn-sm btn-outline-light btn-disabled opacity-50">Mode Tamu</span>
                        <a href="{{ route('admin.login') }}" class="btn btn-sm btn-light">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                    @endif
                </div>
            </div>

            {{-- Bank info --}}
            <div class="bank-info-box mt-3">
                <div class="bank-label"><i class="bi bi-info-circle-fill"></i> Informasi Transfer Pembayaran:</div>
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-bold bank-name">Bank Blu BCA</div>
                        <div class="bank-acct">No. Rek: <strong id="bankNum">0016 6763 2223</strong></div>
                        <div class="bank-holder">A.n Gema Putri Hayatunufus</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-toggle-bank" id="toggleBank">
                        <i class="bi bi-eye" id="bankIcon"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
    </div>
@endif

<div class="container mt-3">

    {{-- Running text --}}
    @if($announcements->isNotEmpty())
        <div class="marquee-wrap mb-3">
            <span class="marquee-label">Informasi :</span>
            <div class="marquee-track">
                <div class="marquee-content">
                    @foreach($announcements as $ann)
                        <span>{{ $ann->content }}</span>
                        <span class="marquee-sep">&#9670;</span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Laporan link --}}
    <a href="{{ route('rekap.index') }}" class="btn btn-laporan w-100 mb-3">
        <i class="bi bi-bar-chart-line-fill"></i> Laporan & Rekap Status Pembayaran Siswa &rarr;
    </a>

    {{-- Chart (left 75%) + stacked stats (right 25%) --}}
    <div class="row g-2 mb-3 align-items-stretch">
        <div class="col-lg-9">
            <div class="content-card chart-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <h6 class="section-title mb-0"><i class="bi bi-bar-chart-line"></i> Grafik Arus Kas Harian</h6>
                    <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                        <label class="mb-0 small text-muted">Periode:</label>
                        <input type="date" name="from" class="form-select form-select-sm" style="width:auto;height:auto;padding:4px 8px;font-size:0.85rem;"
                               value="{{ $from->format('Y-m-d') }}" min="{{ $periodStart->format('Y-m-d') }}" max="{{ $to->format('Y-m-d') }}">
                        <span class="small text-muted">s/d</span>
                        <input type="date" name="to" class="form-select form-select-sm" style="width:auto;height:auto;padding:4px 8px;font-size:0.85rem;"
                               value="{{ $to->format('Y-m-d') }}" min="{{ $from->format('Y-m-d') }}" max="{{ $periodEnd->format('Y-m-d') }}">
                        <button type="submit" class="btn btn-sm btn-primary">Tampilkan</button>
                    </form>
                </div>
                <div class="chart-body">
                    <canvas id="cashFlowChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-3 d-flex flex-column gap-2">
            <div class="stat-card stat-income flex-grow-1">
                <div class="stat-label">PEMASUKAN</div>
                <div class="stat-value">Rp {{ number_format($income, 0, ',', '.') }}</div>
            </div>
            <div class="stat-card stat-expense flex-grow-1">
                <div class="stat-label">PENGELUARAN</div>
                <div class="stat-value">Rp {{ number_format($expense, 0, ',', '.') }}</div>
            </div>
            <div class="stat-card stat-balance flex-grow-1">
                <div class="stat-label">TOTAL SALDO</div>
                <div class="stat-value">Rp {{ number_format($balance, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    {{-- Tabel riwayat --}}
    <div class="content-card">
        <h6 class="section-title mb-2"><i class="bi bi-list-columns-reverse"></i> Laporan Riwayat Transaksi</h6>
        <div id="transactionsWrap" class="table-responsive">
            @include('public._transactions', ['transactions' => $transactions, 'isAdmin' => $isAdmin])
        </div>
    </div>

    @if($isAdmin)
        <form id="bulkDeleteForm" action="{{ route('transactions.bulkDestroy') }}" method="POST" class="d-none">
            @csrf
            <div id="bulkIds"></div>
        </form>

        <div class="modal fade" id="editModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="editForm" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header py-2">
                            <h6 class="modal-title">Edit Transaksi</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-2">
                                <label class="form-label small">Jenis Transaksi</label>
                                <select name="type" id="editType" class="form-select form-select-sm" disabled>
                                    <option value="income">Pemasukan</option>
                                    <option value="expense">Pengeluaran</option>
                                </select>
                                <small class="text-muted d-block mt-1">(jenis transaksi tidak dapat diubah saat edit)</small>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Deskripsi / Keterangan</label>
                                <textarea name="description" class="form-control form-control-sm" rows="2"></textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Nilai Transaksi (Rp)</label>
                                <input type="number" name="amount" class="form-control form-control-sm" min="0" step="0.01" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Bukti (opsional)</label>
                                <input type="file" name="proofs[]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf" multiple>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleBtn = document.getElementById('toggleBank');
            if (toggleBtn) {
                let revealed = false;

                function maskNum() {
                    document.querySelectorAll('.bank-acct strong').forEach(function (num) {
                        num.dataset.real = num.dataset.real || num.textContent;
                        const real = num.dataset.real;
                        num.textContent = real.substring(0, 2) + '••••••••' + real.slice(-2);
                    });
                    document.getElementById('bankIcon').classList.remove('bi-eye-slash');
                    document.getElementById('bankIcon').classList.add('bi-eye');
                }

                function revealNum() {
                    document.querySelectorAll('.bank-acct strong').forEach(function (num) {
                        if (num.dataset.real) num.textContent = num.dataset.real;
                    });
                    document.getElementById('bankIcon').classList.remove('bi-eye');
                    document.getElementById('bankIcon').classList.add('bi-eye-slash');
                }

                maskNum();
                toggleBtn.addEventListener('click', function () {
                    revealed = !revealed;
                    if (revealed) { revealNum(); } else { maskNum(); }
                });
            }

            const ctx = document.getElementById('cashFlowChart');
            if (ctx && typeof Chart !== 'undefined') {
                try {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: @json($labels),
                        datasets: [
                            {
                                label: 'Pemasukan',
                                data: @json($incomeData),
                                backgroundColor: 'rgba(25, 135, 84, 0.8)',
                                borderRadius: 3,
                                barPercentage: 0.6
                            },
                            {
                                label: 'Pengeluaran',
                                data: @json($expenseData),
                                backgroundColor: 'rgba(220, 53, 69, 0.8)',
                                borderRadius: 3,
                                barPercentage: 0.6
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { boxWidth: 14, font: { size: 11 } } },
                            tooltip: {
                                callbacks: {
                                    label: function (c) { return c.dataset.label + ': Rp ' + Number(c.parsed.y).toLocaleString('id-ID'); }
                                }
                            }
                        },
                        scales: {
                            x: { title: { display: true, text: 'Tanggal ({{ $from->format('d M Y') }} - {{ $to->format('d M Y') }})', font: { size: 11 } } },
                            y: {
                                beginAtZero: true,
                                ticks: { callback: function (v) { return (v / 1000).toLocaleString('id-ID') + 'k'; }, font: { size: 10 } }
                            }
                        }
                    }
                });
                } catch (e) {}
            }

            function bindAdmin() {
                const selectAll = document.getElementById('selectAll');
                const rowSelects = document.querySelectorAll('.row-select');
                const bulkBtn = document.getElementById('bulkDeleteBtn');
                const bulkForm = document.getElementById('bulkDeleteForm');
                const bulkIds = document.getElementById('bulkIds');

                function syncBulk() {
                    const checked = [...document.querySelectorAll('.row-select')].filter(c => c.checked);
                    bulkBtn.disabled = checked.length === 0;
                }

                if (selectAll) {
                    selectAll.addEventListener('change', function () {
                        document.querySelectorAll('.row-select').forEach(c => c.checked = selectAll.checked);
                        syncBulk();
                    });
                }
                rowSelects.forEach(c => c.addEventListener('change', syncBulk));

                bulkBtn.addEventListener('click', function () {
                    const checked = [...document.querySelectorAll('.row-select')].filter(c => c.checked);
                    if (!checked.length) return;
                    if (!confirm('Hapus ' + checked.length + ' transaksi terpilih?')) return;
                    bulkIds.innerHTML = '';
                    checked.forEach(c => {
                        const inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'ids[]';
                        inp.value = c.value;
                        bulkIds.appendChild(inp);
                    });
                    bulkForm.submit();
                });

                document.querySelectorAll('.delete-form').forEach(form => {
                    form.addEventListener('submit', function (e) {
                        if (!confirm('Hapus transaksi ini?')) e.preventDefault();
                    });
                });
            }

            var transactionsWrap = document.getElementById('transactionsWrap');

            function bindPagination() {
                transactionsWrap.addEventListener('click', function (e) {
                    var link = e.target.closest('a.page-link');
                    if (!link) return;
                    e.preventDefault();
                    var href = link.getAttribute('href');
                    if (!href) return;
                    var url;
                    try { url = new URL(href, window.location.origin); }
                    catch (err) { return; }
                    fetch(url.pathname + url.search, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (r) { return r.text(); })
                        .then(function (html) {
                            transactionsWrap.innerHTML = html;
                            bindAdmin();
                            transactionsWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        })
                        .catch(function () { window.location.href = href; });
                });
            }

            bindPagination();

            @if($isAdmin)
                const editModal = document.getElementById('editModal');
                const editForm = document.getElementById('editForm');
                editModal.addEventListener('show.bs.modal', function (e) {
                    const btn = e.relatedTarget;
                    editForm.action = '/transactions/' + btn.dataset.id;
                    editForm.type.value = btn.dataset.type;
                    editForm.description.value = btn.dataset.desc || '';
                    editForm.amount.value = btn.dataset.amount;
                });

                bindAdmin();
            @endif
        });
    </script>
@endsection

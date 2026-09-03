@extends('layouts.public')

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6">
            <div class="card korlas-card">
                <div class="card-header">
                    <i class="bi bi-plus-circle me-1"></i> Input Transaksi
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.transactions.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label d-block">Jenis Transaksi</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="type" id="typeIncome" value="income" checked>
                                <label class="form-check-label" for="typeIncome">Pemasukan</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="type" id="typeExpense" value="expense">
                                <label class="form-check-label" for="typeExpense">Pengeluaran</label>
                            </div>
                        </div>

                        {{-- ============ PEMASUKAN ============ --}}
                        <div id="incomeSection">
                            <div class="mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="category" id="category" class="form-select">
                                    <option value="kas">Kas (Rp15.000/bulan)</option>
                                    <option value="komite">Komite (Rp75.000/tahun)</option>
                                    <option value="lain_lain">Lain-lain</option>
                                </select>
                            </div>

                            <div class="mb-3" id="incomeDateWrap">
                                <label class="form-label">Tanggal</label>
                                <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nama</label>
                                <select name="student_name" id="studentName" class="form-select">
                                    <option value="">-- Pilih siswa --</option>
                                    @foreach($students as $s)
                                        <option value="{{ $s }}">{{ $s }}</option>
                                    @endforeach
                                </select>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="notStudent">
                                    <label class="form-check-label" for="notStudent">Bukan dari siswa (input manual)</label>
                                </div>
                                <input type="text" name="manual_name" id="manualName" class="form-control mt-2 d-none" placeholder="Nama penyetor">
                                <input type="hidden" name="name_source" id="nameSource" value="student">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Metode Pembayaran</label>
                                <select name="payment_method" id="paymentMethod" class="form-select">
                                    <option value="transfer">Transfer</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>

                            <div class="mb-3" id="monthsWrap">
                                <label class="form-label">Bulan yang Dibayar (bisa pilih lebih dari satu)</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="selectAllMonths">
                                    <label class="form-check-label fw-bold" for="selectAllMonths">Pilih Semua</label>
                                </div>
                                <div class="row g-2">
                                    @foreach($months as $key => $label)
                                        <div class="col-6 col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input month-check" type="checkbox" name="months[]" value="{{ $key }}" id="m_{{ $key }}" data-month="{{ $key }}">
                                                <label class="form-check-label" for="m_{{ $key }}">{{ $label }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-2" id="kasTotalWrap">
                                    <span class="badge text-bg-info">Total: Rp <span id="kasTotal">0</span></span>
                                    <small class="text-muted">(Rp15.000 &times; <span id="monthCount">0</span> bulan)</small>
                                </div>
                            </div>

                            <div class="mb-3 d-none" id="lainLainAmountWrap">
                                <label class="form-label">Jumlah (Rp)</label>
                                <input type="text" name="amount" id="amountInput" class="form-control" placeholder="0" autocomplete="off">
                                <input type="hidden" name="amount_value" id="amountValue">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Bukti Pembayaran (bisa multiple, akan digabung jadi 1 PDF)</label>
                                <input type="file" name="proofs[]" class="form-control" accept=".jpg,.jpeg,.png,.pdf" multiple>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Deskripsi / Keterangan</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        {{-- ============ PENGELUARAN ============ --}}
                        <div id="expenseSection" class="d-none">
                            <div class="mb-3">
                                <label class="form-label">Tanggal</label>
                                <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Metode Pembayaran</label>
                                <select name="payment_method" class="form-select">
                                    <option value="transfer">Transfer</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nama Penerima (toko / perorangan)</label>
                                <input type="text" name="recipient" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Total Jumlah Pengeluaran (Rp)</label>
                                <input type="text" name="amount" id="expenseAmountInput" class="form-control" placeholder="0" autocomplete="off">
                                <input type="hidden" name="amount_value" id="expenseAmountValue">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Upload Bukti (bisa multiple, akan digabung jadi 1 PDF)</label>
                                <input type="file" name="proofs[]" class="form-control" accept=".jpg,.jpeg,.png,.pdf" multiple>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deskripsi / Keterangan</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                            <a href="/" class="btn btn-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const paidMonths = @json($paidMonths);

            const typeIncome = document.getElementById('typeIncome');
            const typeExpense = document.getElementById('typeExpense');
            const incomeSection = document.getElementById('incomeSection');
            const expenseSection = document.getElementById('expenseSection');

            const category = document.getElementById('category');
            const incomeDateWrap = document.getElementById('incomeDateWrap');
            const monthsWrap = document.getElementById('monthsWrap');
            const kasTotalWrap = document.getElementById('kasTotalWrap');
            const lainLainAmountWrap = document.getElementById('lainLainAmountWrap');

            const notStudent = document.getElementById('notStudent');
            const studentName = document.getElementById('studentName');
            const manualName = document.getElementById('manualName');
            const nameSource = document.getElementById('nameSource');

            const monthChecks = document.querySelectorAll('.month-check');
            const selectAllMonths = document.getElementById('selectAllMonths');
            const kasTotal = document.getElementById('kasTotal');
            const monthCount = document.getElementById('monthCount');

            const amountInput = document.getElementById('amountInput');
            const amountValue = document.getElementById('amountValue');
            const expenseAmountInput = document.getElementById('expenseAmountInput');
            const expenseAmountValue = document.getElementById('expenseAmountValue');

            function syncType() {
                const expense = typeExpense.checked;
                incomeSection.classList.toggle('d-none', expense);
                expenseSection.classList.toggle('d-none', !expense);
                incomeSection.querySelectorAll('input, select, textarea').forEach(el => el.disabled = expense);
                expenseSection.querySelectorAll('input, select, textarea').forEach(el => el.disabled = !expense);
            }

            function syncCategory() {
                const cat = category.value;
                incomeDateWrap.classList.toggle('d-none', cat === 'kas');
                monthsWrap.classList.toggle('d-none', cat !== 'kas');
                lainLainAmountWrap.classList.toggle('d-none', cat === 'kas');
                kasTotalWrap.classList.toggle('d-none', cat !== 'kas');
                updateKasTotal();
            }

            function updateKasTotal() {
                const count = [...monthChecks].filter(c => c.checked && !c.disabled).length;
                monthCount.textContent = count;
                kasTotal.textContent = (count * 15000).toLocaleString('id-ID');
                syncSelectAll();
            }

            function syncSelectAll() {
                const available = [...monthChecks].filter(c => !c.disabled);
                selectAllMonths.checked = available.length > 0 && available.every(c => c.checked);
            }

            function syncPaidMonths() {
                const selectedName = studentName.value.trim().toUpperCase();
                const paid = paidMonths[selectedName] || {};

                monthChecks.forEach(cb => {
                    const m = cb.dataset.month;
                    if (paid[m]) {
                        cb.checked = false;
                        cb.disabled = true;
                        cb.closest('.form-check').style.opacity = '0.5';
                        cb.closest('.form-check').title = 'Sudah dibayar';
                    } else {
                        cb.disabled = false;
                        cb.closest('.form-check').style.opacity = '1';
                        cb.closest('.form-check').title = '';
                    }
                });

                updateKasTotal();
            }

            function formatRupiah(input, hiddenInput) {
                input.addEventListener('input', function () {
                    let val = this.value.replace(/[^\d]/g, '');
                    if (val === '') {
                        this.value = '';
                        hiddenInput.value = '';
                        return;
                    }
                    this.value = parseInt(val, 10).toLocaleString('id-ID');
                    hiddenInput.value = val;
                });
            }

            if (amountInput && amountValue) {
                formatRupiah(amountInput, amountValue);
            }
            if (expenseAmountInput && expenseAmountValue) {
                formatRupiah(expenseAmountInput, expenseAmountValue);
            }

            typeIncome.addEventListener('change', syncType);
            typeExpense.addEventListener('change', syncType);
            category.addEventListener('change', syncCategory);
            monthChecks.forEach(c => c.addEventListener('change', updateKasTotal));
            selectAllMonths.addEventListener('change', function () {
                monthChecks.forEach(cb => {
                    if (!cb.disabled) cb.checked = selectAllMonths.checked;
                });
                updateKasTotal();
            });
            studentName.addEventListener('change', syncPaidMonths);

            notStudent.addEventListener('change', function () {
                if (notStudent.checked) {
                    nameSource.value = 'manual';
                    studentName.disabled = true;
                    manualName.classList.remove('d-none');
                    monthChecks.forEach(cb => {
                        cb.disabled = false;
                        cb.closest('.form-check').style.opacity = '1';
                        cb.closest('.form-check').title = '';
                    });
                } else {
                    nameSource.value = 'student';
                    studentName.disabled = false;
                    manualName.classList.add('d-none');
                    syncPaidMonths();
                }
            });

            syncType();
            syncCategory();
            syncPaidMonths();
        });
    </script>
@endsection

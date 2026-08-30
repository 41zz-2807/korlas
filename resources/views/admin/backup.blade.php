@extends('layouts.public')

@section('content')
<style>
    .backup-card {
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }
    .backup-title { font-size: 1.15rem; font-weight: 700; color: #1e3a8a; }
    .backup-icon {
        width: 44px; height: 44px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 1.3rem;
    }
    .row-file { font-size: 0.8rem; color: #64748b; }
</style>

<div class="container py-4">
    <div class="mb-3">
        <a href="{{ route('home') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
    @endif

    <h4 class="mb-3 backup-title"><i class="bi bi-gear me-1"></i> Pusat Backup &amp; Restore</h4>

    <div class="row g-3">
        {{-- Backup DB --}}
        <div class="col-md-4">
            <div class="card backup-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <span class="backup-icon bg-primary-subtle text-primary me-2"><i class="bi bi-database"></i></span>
                        <div>
                            <div class="fw-bold">Backup Database</div>
                            <div class="row-file">File: database.sqlite</div>
                        </div>
                    </div>
                    <div class="mb-3 row-file">
                        Ukuran: <b>{{ $dbSize }}</b> &middot; Terakhir: {{ $dbModified }}
                    </div>
                    <a href="{{ route('admin.backup.db') }}" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-download"></i> Unduh Backup DB
                    </a>
                </div>
            </div>
        </div>

        {{-- Backup Uploads --}}
        <div class="col-md-4">
            <div class="card backup-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <span class="backup-icon bg-success-subtle text-success me-2"><i class="bi bi-file-earmark-zip"></i></span>
                        <div>
                            <div class="fw-bold">Backup File Upload</div>
                            <div class="row-file">Folder: storage/app/public</div>
                        </div>
                    </div>
                    <div class="mb-3 row-file">Berisi file bukti upload (.zip)</div>
                    <a href="{{ route('admin.backup.uploads') }}" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-file-earmark-zip"></i> Unduh Backup Upload
                    </a>
                </div>
            </div>
        </div>

        {{-- Restore DB --}}
        <div class="col-md-4">
            <div class="card backup-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <span class="backup-icon bg-warning-subtle text-warning me-2"><i class="bi bi-arrow-counterclockwise"></i></span>
                        <div class="fw-bold">Restore Database</div>
                    </div>
                    <div class="mb-2 row-file">Upload file backup .sqlite. Database saat ini akan di-backup otomatis.</div>
                    <form method="POST" action="{{ route('admin.restore.db') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="db_file" class="form-control form-control-sm mb-2" accept=".sqlite,.db" required>
                        <button type="submit" class="btn btn-warning btn-sm w-100" onclick="return confirm('Yakin restore database? Data saat ini akan di-backup otomatis.')">
                            <i class="bi bi-arrow-counterclockwise"></i> Restore DB
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Telegram Report --}}
    <div class="card backup-card mt-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-bold"><i class="bi bi-telegram me-1"></i> Laporan Telegram</div>
                    <div class="row-file">Kirim rekap singkat pemasukan/pengeluaran/saldo + file PDF ke Telegram.</div>
                </div>
                <form method="POST" action="{{ route('admin.report.telegram') }}" class="d-flex gap-2">
                    @csrf
                    <select name="kategori" class="form-select form-select-sm" style="width:auto;">
                        <option value="kas">Kas Kelas</option>
                        <option value="komite">Komite</option>
                    </select>
                    <button type="submit" class="btn btn-info btn-sm text-white">
                        <i class="bi bi-send"></i> Kirim Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

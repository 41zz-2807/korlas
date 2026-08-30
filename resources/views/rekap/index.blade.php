@extends('layouts.public')

@section('content')
<style>
    .rekap-page {
        --rekap-primary: #1e3a8a;
        --rekap-light: #2563eb;
        --rekap-border: #e2e8f0;
    }
    .rekap-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .btn-rekap-back {
        display: inline-block;
        padding: 8px 14px;
        background: linear-gradient(135deg, #94a3b8, #475569);
        color: #fff;
        border-radius: 9px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        box-shadow: 0 2px 6px rgba(15,23,42,0.10);
        transition: all 0.18s ease;
    }
    .btn-rekap-back:hover { background: linear-gradient(135deg, #cbd5e1, #64748b); color: #fff; transform: translateY(-1px); }
    .rekap-title { font-size: 1.2rem; font-weight: 700; color: #1e3a8a; margin: 0; }
    .rekap-card {
        background: #fff;
        padding: 18px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .rekap-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        border-bottom: 2px solid #f1f5f9;
        padding-bottom: 10px;
        margin-bottom: 4px;
    }
    .btn-export {
        padding: 8px 14px;
        background: linear-gradient(135deg, #4ade80, #15803d);
        color: #fff;
        border-radius: 9px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(21,128,61,0.30);
        transition: all 0.18s ease;
    }
    .btn-export:hover { background: linear-gradient(135deg, #86efac, #22c55e); transform: translateY(-1px); }
    .rekap-form-control {
        padding: 8px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.9rem;
    }
    .rekap-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.82rem; }
    .rekap-table th, .rekap-table td { padding: 7px 5px; border: 1px solid #e2e8f0; }
    .rekap-table th {
        background-color: #f8fafc;
        color: #64748b;
        font-weight: 600;
        text-align: center;
        white-space: nowrap;
    }
    .rekap-table td:first-child { font-weight: 600; min-width: 170px; }
    .status-check { color: #16a34a; font-weight: bold; text-align: center; }
    .status-cross { color: #cbd5e1; text-align: center; }
</style>

<div class="container mt-3">
    <div class="rekap-bar">
        <a href="{{ route('home') }}" class="btn-rekap-back">&larr; Kembali ke Dashboard Utama</a>
        <h2 class="rekap-title">&#128202; Laporan &amp; Rekap Keuangan</h2>
    </div>

    <div class="rekap-card">
        <div class="rekap-toolbar">
            <form method="GET" action="{{ route('rekap.index') }}" style="display:flex;align-items:center;gap:8px;">
                <label style="font-weight:bold;font-size:0.85rem;">Pilih Kategori:</label>
                <select name="kategori" class="rekap-form-control" onchange="this.form.submit()">
                    <option value="kas" {{ $kategori === 'kas' ? 'selected' : '' }}>Kas Kelas</option>
                    <option value="komite" {{ $kategori === 'komite' ? 'selected' : '' }}>Komite</option>
                </select>
            </form>

            <form method="GET" action="{{ route('rekap.export') }}" target="_blank" style="display:flex;align-items:center;gap:6px;">
                <input type="hidden" name="kategori" value="{{ $kategori }}">
                <select name="export" class="rekap-form-control" style="font-weight:bold;" required>
                    <option value="siswa_pdf">&#128196; Export PDF (Cetak / Print)</option>
                    <option value="siswa_xlsx">&#128202; Export Excel (.XLSX)</option>
                </select>
                <button type="submit" class="btn-export">Proses Export</button>
            </form>
        </div>

        <div style="overflow-x:auto;">
            <table class="rekap-table">
                <thead>
                    <tr>
                        <th>Nama Siswa</th>
                        @foreach($months as $meta)
                            <th style="min-width:64px;">{{ $meta['name'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td>{{ ucwords(strtolower($student)) }}</td>
                            @foreach($months as $m => $meta)
                                @if(isset($paid[strtoupper(trim($student))][$m]))
                                    <td class="status-check">&#10004;</td>
                                @else
                                    <td class="status-cross">-</td>
                                @endif
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="13" class="text-center text-muted py-4">Belum ada data siswa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="text-align:right;font-size:0.75rem;color:#64748b;margin-top:12px;font-style:italic;">
            Kategori: {{ $kategoriLabel }} &mdash; Tahun Ajaran {{ now()->month >= 7 ? now()->year . '/' . (now()->year+1) : (now()->year-1) . '/' . now()->year }}
        </div>
    </div>
</div>
@endsection

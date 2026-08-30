<table class="table table-hover align-middle mb-2">
    <thead>
        <tr>
            @if($isAdmin)
                <th width="30"><input type="checkbox" id="selectAll"></th>
            @endif
            <th>Tanggal</th>
            <th>Nama</th>
            <th>Metode</th>
            <th>Keterangan</th>
            <th class="text-end">Nominal</th>
            <th class="text-center" width="100">Aksi</th>
        </tr>
    </thead>
    <tbody>
        @forelse($transactions as $trx)
            <tr>
                @if($isAdmin)
                    <td><input type="checkbox" class="row-select" value="{{ $trx->id }}"></td>
                @endif
                <td class="nowrap">{{ $trx->transaction_date->format('d/m/Y') }}</td>
                <td class="fw-semibold">{{ $trx->name }}</td>
                <td><span class="badge badge-method">{{ strtoupper($trx->payment_method) }}</span></td>
                <td>
                    {{ $trx->description ?? '-' }}
                    @if($trx->category)
                        <span class="badge badge-cat">{{ ucwords(str_replace('_', ' ', $trx->category)) }}</span>
                    @endif
                    @if($trx->months && count($trx->months))
                        <div><small class="text-muted">{{ implode(', ', $trx->months) }}</small></div>
                    @endif
                </td>
                <td class="text-end fw-bold {{ $trx->type === 'income' ? 'text-success' : 'text-danger' }}">
                    {{ $trx->type === 'income' ? '+' : '-' }} Rp {{ number_format($trx->amount, 0, ',', '.') }}
                </td>
                <td class="text-center">
                    @if($trx->proof_path)
                        <a href="{{ route('transactions.proof', $trx) }}" class="btn btn-sm btn-proof" title="Unduh Bukti">
                            <i class="bi bi-download"></i>
                        </a>
                    @endif
                    @if($isAdmin)
                        <button type="button" class="btn btn-sm btn-edit" title="Edit" data-bs-toggle="modal" data-bs-target="#editModal"
                            data-id="{{ $trx->id }}"
                            data-date="{{ $trx->transaction_date->format('Y-m-d') }}"
                            data-name="{{ $trx->name }}"
                            data-method="{{ $trx->payment_method }}"
                            data-desc="{{ $trx->description }}"
                            data-type="{{ $trx->type }}"
                            data-amount="{{ $trx->amount }}"
                            data-category="{{ $trx->category }}"
                            data-recipient="{{ $trx->recipient }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route('transactions.destroy', $trx) }}" method="POST" class="d-inline delete-form">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-delete" title="Hapus">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $isAdmin ? 7 : 6 }}" class="text-center text-muted py-4">Belum ada transaksi.</td>
            </tr>
        @endforelse
    </tbody>
</table>
<div class="d-flex justify-content-between align-items-center">
    @if($isAdmin)
        <button type="button" class="btn btn-sm btn-danger" id="bulkDeleteBtn" disabled>
            <i class="bi bi-trash3"></i> Hapus Terpilih
        </button>
    @else
        <div></div>
    @endif
    <div id="paginationWrap">{{ $transactions->links('pagination::bootstrap-5') }}</div>
</div>

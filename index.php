<?php
// ===== KONFIGURASI DATABASE =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'uts_penjualan');
define('DB_USER', 'root');
define('DB_PASS', '1234');

// Blok API: hanya jalan untuk permintaan data (POST / ?t= / ?opt=), selain itu tampilkan halaman HTML di bawah
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['t']) || isset($_GET['opt'])) {
header('Content-Type: application/json; charset=utf-8');
function out($data, int $code = 200) { http_response_code($code); echo json_encode($data); exit; }

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    out(['error' => 'Koneksi database gagal: ' . $e->getMessage()], 500);
}

// Tabel yang boleh diakses: list dibaca dari VIEW, simpan/ubah/hapus ke TABEL
$T = [
    'role'             => ['pk' => 'idrole',             'view' => 'v_master_role',   'cols' => ['nama_role']],
    'user'             => ['pk' => 'iduser',             'view' => 'v_master_user',   'cols' => ['username', 'password', 'idrole']],
    'margin_penjualan' => ['pk' => 'idmargin_penjualan', 'view' => 'v_master_margin', 'cols' => ['persen', 'status', 'iduser']],
    'satuan'           => ['pk' => 'idsatuan',           'view' => 'v_master_satuan', 'cols' => ['nama_satuan', 'status']],
    'vendor'           => ['pk' => 'idvendor',           'view' => 'v_master_vendor', 'cols' => ['nama_vendor', 'badan_hukum', 'status']],
    'barang'           => ['pk' => 'idbarang',           'view' => 'v_master_barang', 'cols' => ['jenis', 'nama', 'idsatuan', 'status', 'harga']],
];
$OPT = [
    'role'   => 'SELECT idrole v, nama_role l FROM role ORDER BY 1',
    'user'   => 'SELECT iduser v, username l FROM user ORDER BY 1',
    'satuan' => 'SELECT idsatuan v, nama_satuan l FROM satuan ORDER BY 1',
];

function stokTerakhir(PDO $pdo, int $idbarang): int {
    $st = $pdo->prepare('SELECT stock FROM kartu_stok WHERE idbarang = ? ORDER BY idkartu_stok DESC LIMIT 1 FOR UPDATE');
    $st->execute([$idbarang]);
    return (int)($st->fetchColumn() ?: 0);
}
function catatStok(PDO $pdo, int $idbarang, int $masuk, int $keluar): void {
    $stok = stokTerakhir($pdo, $idbarang) + $masuk - $keluar;
    if ($stok < 0) throw new Exception('Stok tidak mencukupi!');
    // jenis_transaksi 'M' = penyesuaian manual, idtransaksi 0 karena bukan dari dokumen transaksi
    $pdo->prepare('INSERT INTO kartu_stok (jenis_transaksi, masuk, keluar, stock, idtransaksi, idbarang) VALUES (?,?,?,?,0,?)')
        ->execute(['M', $masuk, $keluar, $stok, $idbarang]);
}

try {
    // ---------- GET ----------
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['opt'])) {
            if (!isset($OPT[$_GET['opt']])) out(['error' => 'Opsi tidak dikenal'], 400);
            out($pdo->query($OPT[$_GET['opt']])->fetchAll());
        }
        $t = $_GET['t'] ?? '';
        if (!isset($T[$t])) out(['error' => 'Tabel tidak dikenal'], 400);
        $c = $T[$t];
        if (isset($_GET['id'])) {   // satu baris mentah untuk form edit
            $st = $pdo->prepare("SELECT * FROM `$t` WHERE `{$c['pk']}` = ?");
            $st->execute([$_GET['id']]);
            out($st->fetch() ?: []);
        }
        if ($t === 'barang') {
            $sql = "SELECT v.*, COALESCE((SELECT k.stock FROM kartu_stok k WHERE k.idbarang = v.idbarang
                    ORDER BY k.idkartu_stok DESC LIMIT 1), 0) AS stok FROM `{$c['view']}` v ORDER BY v.idbarang";
        } else {
            $sql = "SELECT * FROM `{$c['view']}` ORDER BY 1";
        }
        out($pdo->query($sql)->fetchAll());
    }

    // ---------- POST ----------
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $t = $in['t'] ?? '';
    $aksi = $in['aksi'] ?? '';
    if (!isset($T[$t])) out(['error' => 'Tabel tidak dikenal'], 400);
    $c = $T[$t];
    $id = $in['id'] ?? null;

    if ($aksi === 'hapus') {
        $pdo->beginTransaction();
        if ($t === 'barang') $pdo->prepare('DELETE FROM kartu_stok WHERE idbarang = ?')->execute([$id]);
        $pdo->prepare("DELETE FROM `$t` WHERE `{$c['pk']}` = ?")->execute([$id]);
        $pdo->commit();
        out(['ok' => 'Data dihapus.']);
    }

    if ($aksi === 'stok' && $t === 'barang') {
        $jml = (int)($in['jumlah'] ?? 0);
        if ($jml <= 0) throw new Exception('Jumlah harus lebih dari 0.');
        $pdo->beginTransaction();
        if (($in['arah'] ?? '') === 'masuk') catatStok($pdo, (int)$id, $jml, 0);
        else catatStok($pdo, (int)$id, 0, $jml);
        $pdo->commit();
        out(['ok' => 'Stok diperbarui.']);
    }

    if ($aksi === 'simpan') {
        $data = $in['data'] ?? [];
        $val = [];
        foreach ($c['cols'] as $col) {
            $v = trim((string)($data[$col] ?? ''));
            if ($v === '') throw new Exception("Kolom $col wajib diisi.");
            $val[] = $v;
        }
        $pdo->beginTransaction();
        if (!$id) {
            $sql = "INSERT INTO `$t` (" . implode(',', array_map(fn($x) => "`$x`", $c['cols'])) . ') VALUES (' . implode(',', array_fill(0, count($val), '?')) . ')';
            $pdo->prepare($sql)->execute($val);
            if ($t === 'barang' && (int)($data['stok'] ?? 0) > 0) {
                catatStok($pdo, (int)$pdo->lastInsertId(), (int)$data['stok'], 0);
            }
        } else {
            $sql = "UPDATE `$t` SET " . implode(',', array_map(fn($x) => "`$x` = ?", $c['cols'])) . " WHERE `{$c['pk']}` = ?";
            $pdo->prepare($sql)->execute([...$val, $id]);
        }
        $pdo->commit();
        out(['ok' => 'Data tersimpan.']);
    }
    out(['error' => 'Aksi tidak dikenal'], 400);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $msg = $e->getCode() === '23000'
        ? 'Data masih dipakai tabel lain atau referensinya tidak valid (cek ID Role / Satuan / User).'
        : $e->getMessage();
    out(['error' => $msg], 400);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    out(['error' => $e->getMessage()], 400);
}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Penjualan & Pengadaan Barang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border-radius: 10px; border: none; box-shadow: 0 4px 8px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .table-custom th { background-color: #0d6efd; color: white; text-align: center; }
        .table-custom td { text-align: center; vertical-align: middle; }
        .nav-tabs .nav-link.active { font-weight: bold; border-bottom: 3px solid #0d6efd; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#"><i class="bi bi-box-seam me-2"></i>Sistem Informasi Penjualan & Pengadaan Barang</a>
        </div>
    </nav>

    <div class="container">
        <div id="alertBox"></div>
        <ul class="nav nav-tabs mb-4" id="masterTab"></ul>
        <div class="tab-content" id="masterTabContent"></div>
    </div>

    <!-- Modal form tunggal (isinya dibuat otomatis per tabel) -->
    <div class="modal fade" id="modalForm" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" onclick="simpan()">Simpan Data</button>
            </div>
        </div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API = 'index.php';
        const AKTIF = [[1, '1 (Aktif)'], [0, '0 (Non-Aktif)']];

        // Konfigurasi tiap tab: nama tabel di database => tampilan & field form
        const CFG = {
            barang: { tab: 'Master Barang & Stok', judul: 'Barang', icon: 'bi-box', stok: true, fields: [
                { n: 'jenis', l: 'Jenis', type: 'text', max: 1, ph: 'A/B/C' },
                { n: 'nama', l: 'Nama Barang', type: 'text' },
                { n: 'idsatuan', l: 'Satuan', type: 'select', opt: 'satuan' },
                { n: 'harga', l: 'Harga (Rp)', type: 'number' },
                { n: 'status', l: 'Status', type: 'select', list: AKTIF },
                { n: 'stok', l: 'Stok Awal', type: 'number', def: 10, barukan: true } ] },
            role: { tab: 'Role', judul: 'Role', icon: 'bi-person-badge', fields: [
                { n: 'nama_role', l: 'Nama Role', type: 'text' } ] },
            user: { tab: 'User', judul: 'User', icon: 'bi-people', fields: [
                { n: 'username', l: 'Username', type: 'text' },
                { n: 'password', l: 'Password', type: 'text' },
                { n: 'idrole', l: 'Role', type: 'select', opt: 'role' } ] },
            margin_penjualan: { tab: 'Margin Penjualan', judul: 'Margin Penjualan', icon: 'bi-percent', fields: [
                { n: 'persen', l: 'Persentase Margin (%)', type: 'number', step: '0.1' },
                { n: 'status', l: 'Status Margin', type: 'select', list: [[0, '0 (Non-Aktif)'], [1, '1 (Aktif)']] },
                { n: 'iduser', l: 'Diubah Oleh', type: 'select', opt: 'user' } ] },
            satuan: { tab: 'Satuan', judul: 'Satuan', icon: 'bi-tags', fields: [
                { n: 'nama_satuan', l: 'Nama Satuan', type: 'text' },
                { n: 'status', l: 'Status', type: 'select', list: AKTIF } ] },
            vendor: { tab: 'Vendor', judul: 'Vendor', icon: 'bi-building', fields: [
                { n: 'nama_vendor', l: 'Nama Vendor', type: 'text' },
                { n: 'badan_hukum', l: 'Badan Hukum (Y/N)', type: 'select', list: [['Y', 'Y (Ya)'], ['N', 'N (Tidak)']] },
                { n: 'status', l: 'Status (A/N)', type: 'select', list: [['A', 'A (Aktif)'], ['N', 'N (Non-Aktif)']] } ] }
        };

        let aktif = null, editId = null;
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const modal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('modalForm'));

        async function api(url, body) {
            const opt = body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {};
            const r = await fetch(url, opt);
            const d = await r.json().catch(() => ({ error: 'Respons server tidak valid. Pastikan dibuka lewat http://localhost/, bukan file langsung.' }));
            if (!r.ok) throw new Error(d.error || 'Terjadi kesalahan');
            return d;
        }
        function pesan(teks, tipe = 'success') {
            document.getElementById('alertBox').innerHTML =
                `<div class="alert alert-${tipe} alert-dismissible fade show">${esc(teks)}<button class="btn-close" data-bs-dismiss="alert"></button></div>`;
        }

        // ---------- Bangun tab & panel ----------
        function bangunHalaman() {
            let nav = '', pane = '', i = 0;
            for (const [t, c] of Object.entries(CFG)) {
                nav += `<li class="nav-item"><button class="nav-link ${i ? '' : 'active'}" data-bs-toggle="tab" data-bs-target="#tab-${t}">${c.tab}</button></li>`;
                pane += `<div class="tab-pane fade ${i ? '' : 'show active'}" id="tab-${t}"><div class="card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi ${c.icon} me-2"></i>Kelola Data ${c.judul}</h5>
                        <button class="btn btn-primary btn-sm" onclick="bukaForm('${t}')"><i class="bi bi-plus-lg me-1"></i>Tambah ${c.judul}</button>
                    </div>
                    <div class="table-responsive"><table class="table table-bordered table-striped table-hover table-custom">
                        <thead id="thead-${t}"></thead><tbody id="tbody-${t}"></tbody></table></div></div></div>`;
                i++;
            }
            document.getElementById('masterTab').innerHTML = nav;
            document.getElementById('masterTabContent').innerHTML = pane;
        }

        // ---------- Tampilkan data dari database (lewat VIEW) ----------
        async function muat(t) {
            const c = CFG[t];
            try {
                const rows = await api(`${API}?t=${t}`);
                const cols = rows.length ? Object.keys(rows[0]) : [];
                const pk = cols[0];
                document.getElementById('thead-' + t).innerHTML = rows.length
                    ? '<tr>' + cols.map(k => `<th>${esc(k)}</th>`).join('') + (c.stok ? '<th>kelola stok</th>' : '') + '<th>aksi</th></tr>' : '';
                document.getElementById('tbody-' + t).innerHTML = rows.length ? rows.map(r => {
                    const sel = cols.map(k => {
                        let v = r[k];
                        if (v === 'Aktif' || v === 'Non-Aktif') return `<td><span class="badge ${v === 'Aktif' ? 'bg-success' : 'bg-secondary'}">${v}</span></td>`;
                        if (k === 'harga') return `<td>Rp ${Number(v).toLocaleString('id-ID')}</td>`;
                        if (k === 'persen') return `<td>${esc(v)}%</td>`;
                        if (k === 'stok') return `<td><span class="fw-bold text-primary fs-6">${esc(v)}</span></td>`;
                        return `<td>${esc(v)}</td>`;
                    }).join('');
                    const stok = c.stok ? `<td>
                        <button class="btn btn-sm btn-outline-success me-1" onclick="ubahStok(${r[pk]}, 'masuk')"><i class="bi bi-plus-circle me-1"></i>Tambah</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="ubahStok(${r[pk]}, 'keluar')"><i class="bi bi-dash-circle me-1"></i>Kurang</button></td>` : '';
                    return `<tr>${sel}${stok}<td>
                        <button class="btn btn-sm btn-warning text-dark fw-bold me-1" onclick="bukaForm('${t}', ${r[pk]})"><i class="bi bi-pencil-fill"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="hapus('${t}', ${r[pk]})"><i class="bi bi-trash"></i></button></td></tr>`;
                }).join('') : `<tr><td class="text-muted py-4">Belum ada data.</td></tr>`;
            } catch (e) { pesan(e.message, 'danger'); }
        }

        // ---------- Form tambah / ubah ----------
        async function bukaForm(t, id = null) {
            aktif = t; editId = id;
            const c = CFG[t];
            try {
                const row = id ? await api(`${API}?t=${t}&id=${id}`) : {};
                let html = '';
                for (const f of c.fields) {
                    if (f.barukan && id) continue;          // stok awal hanya saat tambah barang
                    const val = id ? (row[f.n] ?? '') : (f.def ?? '');
                    let input;
                    if (f.type === 'select') {
                        const opsi = f.opt ? (await api(`${API}?opt=${f.opt}`)).map(o => [o.v, o.l]) : f.list;
                        input = `<select class="form-select" id="f_${f.n}">` +
                            opsi.map(([v, l]) => `<option value="${esc(v)}" ${String(v) === String(val) ? 'selected' : ''}>${esc(l)}</option>`).join('') + '</select>';
                    } else {
                        input = `<input type="${f.type}" class="form-control" id="f_${f.n}" value="${esc(val)}"
                            ${f.max ? `maxlength="${f.max}"` : ''} ${f.step ? `step="${f.step}"` : ''} ${f.ph ? `placeholder="${f.ph}"` : ''}>`;
                    }
                    html += `<div class="mb-3"><label class="form-label fw-bold">${f.l}</label>${input}</div>`;
                }
                document.getElementById('modalBody').innerHTML = html;
                document.getElementById('modalTitle').innerText = (id ? 'Edit Data ' : 'Tambah ') + c.judul + (id ? '' : ' Baru');
                modal().show();
            } catch (e) { pesan(e.message, 'danger'); }
        }
        async function simpan() {
            const data = {};
            CFG[aktif].fields.forEach(f => { const el = document.getElementById('f_' + f.n); if (el) data[f.n] = el.value; });
            try {
                const r = await api(API, { t: aktif, aksi: 'simpan', id: editId, data });
                modal().hide(); pesan(r.ok); muat(aktif);
            } catch (e) { alert(e.message); }
        }
        async function hapus(t, id) {
            if (!confirm('Yakin ingin menghapus data ini?')) return;
            try { const r = await api(API, { t, aksi: 'hapus', id }); pesan(r.ok); muat(t); }
            catch (e) { pesan(e.message, 'danger'); }
        }
        async function ubahStok(id, arah) {
            const jml = prompt(`Masukkan jumlah stok yang ingin ${arah === 'masuk' ? 'DITAMBAHKAN' : 'DIKURANGI'}:`, '5');
            if (!jml || isNaN(jml) || parseInt(jml) <= 0) return;
            try { const r = await api(API, { t: 'barang', aksi: 'stok', id, arah, jumlah: parseInt(jml) }); pesan(r.ok); muat('barang'); }
            catch (e) { pesan(e.message, 'danger'); }
        }

        document.addEventListener('DOMContentLoaded', () => { bangunHalaman(); Object.keys(CFG).forEach(muat); });
    </script>
</body>
</html>
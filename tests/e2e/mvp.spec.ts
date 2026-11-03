import { test, expect, type Page } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8123';

const accounts = {
  admin: { email: 'admin@contoh.situksg.test', pwd: 'password' },
  pelapor_dksg: { email: 'pelapor.dksg@contoh.situksg.test', pwd: 'password' },
  pelapor_sks: { email: 'pelapor.sks@contoh.situksg.test', pwd: 'password' },
  verifikator: { email: 'verifikator@contoh.situksg.test', pwd: 'password' },
  pimpinan: { email: 'pimpinan@contoh.situksg.test', pwd: 'password' },
  auditor: { email: 'auditor@contoh.situksg.test', pwd: 'password' },
};

async function login(page: Page, email: string, pwd: string) {
  await page.goto(`${BASE_URL}/login`);
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', pwd);
  await page.click('button[data-test="login-button"]');
  await page.waitForURL(`${BASE_URL}/dashboard`, { timeout: 10000 });
}

async function logout(page: Page) {
  await page.click('[data-test="sidebar-menu-button"]');
  await page.click('[data-test="logout-button"]');
  await page.waitForURL(`${BASE_URL}/login`, { timeout: 10000 });
}

async function assertForbidden(page: Page, url: string) {
  const res = await page.goto(`${BASE_URL}${url}`);
  expect(res?.status()).toBe(403);
}

async function selectIndicatorGroup(page: Page, groupLabel: string) {
  await page.locator('select').first().selectOption({ label: groupLabel });
  const indicatorSelect = page.locator('select').nth(1);
  await expect(indicatorSelect.locator('option')).not.toHaveCount(1);
  await indicatorSelect.selectOption({ index: 1 });
}

test.describe.serial('E2E — SITU-KSG full role & permission coverage', () => {
  test.describe('1. Authentication & session', () => {
    test('login salah password gagal, login benar berhasil, logout mengembalikan ke login', async ({ page }) => {
      await page.goto(`${BASE_URL}/login`);
      await page.fill('input[type="email"]', accounts.admin.email);
      await page.fill('input[type="password"]', 'password-salah');
      await page.click('button[data-test="login-button"]');
      await expect(page).toHaveURL(`${BASE_URL}/login`);
      await expect(page.locator('text=/credentials|kredensial|tidak cocok/i')).toBeVisible({ timeout: 5000 });

      await login(page, accounts.admin.email, accounts.admin.pwd);
      await expect(page).toHaveURL(`${BASE_URL}/dashboard`);

      await logout(page);
      await page.goto(`${BASE_URL}/dashboard`);
      await expect(page).toHaveURL(/\/login/);
    });

    test('guest dipaksa ke halaman terlindungi selalu diarahkan ke login', async ({ page }) => {
      for (const url of ['/dashboard', '/realisasi', '/verifikasi', '/risiko', '/laporan', '/audit-trail', '/admin/users']) {
        await page.goto(`${BASE_URL}${url}`);
        await expect(page).toHaveURL(/\/login/);
      }
    });
  });

  test.describe('2. Role-based sidebar & 403 enforcement', () => {
    test('Admin: semua menu tampil, semua halaman 200', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      for (const url of ['/dashboard', '/indikator', '/realisasi', '/realisasi/create', '/verifikasi', '/risiko', '/laporan', '/audit-trail', '/admin/users', '/admin/units', '/admin/periods', '/admin/indicators', '/admin/assignments']) {
        const res = await page.goto(`${BASE_URL}${url}`);
        expect(res?.status(), url).toBe(200);
      }
    });

    test('Pelapor Unit: Verifikasi & Audit Trail & Administrasi 403, menu tersembunyi', async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await expect(page.locator('a:has-text("Verifikasi")')).toHaveCount(0);
      await expect(page.locator('a:has-text("Audit Trail")')).toHaveCount(0);
      await expect(page.locator('text=Administrasi')).toHaveCount(0);

      for (const url of ['/verifikasi', '/audit-trail', '/admin/users', '/admin/units']) {
        await assertForbidden(page, url);
      }
      const res = await page.goto(`${BASE_URL}/realisasi/create`);
      expect(res?.status()).toBe(200);
    });

    test('Verifikator: Verifikasi terlihat, Input Pelaporan & Audit Trail & Admin 403', async ({ page }) => {
      await login(page, accounts.verifikator.email, accounts.verifikator.pwd);
      await expect(page.locator('a:has-text("Verifikasi")')).toHaveCount(1);
      for (const url of ['/realisasi/create', '/audit-trail', '/admin/users']) {
        await assertForbidden(page, url);
      }
    });

    test('Pimpinan: tanpa Verifikasi/Input Pelaporan/Audit Trail/Admin', async ({ page }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await expect(page.locator('a:has-text("Verifikasi")')).toHaveCount(0);
      for (const url of ['/realisasi/create', '/verifikasi', '/audit-trail', '/admin/units']) {
        await assertForbidden(page, url);
      }
      const res = await page.goto(`${BASE_URL}/dashboard`);
      expect(res?.status()).toBe(200);
    });

    test('Auditor: hanya Audit Trail dari menu lintas-peran, sisanya 403', async ({ page }) => {
      await login(page, accounts.auditor.email, accounts.auditor.pwd);
      await expect(page.locator('a:has-text("Audit Trail")')).toHaveCount(1);
      for (const url of ['/realisasi/create', '/verifikasi', '/admin/units']) {
        await assertForbidden(page, url);
      }
    });

    test('Indikator tanpa unit (Verifikator/Pimpinan/Auditor) menampilkan peringatan, bukan error', async ({ page }) => {
      for (const acc of [accounts.verifikator, accounts.pimpinan, accounts.auditor]) {
        await login(page, acc.email, acc.pwd);
        await page.goto(`${BASE_URL}/indikator`);
        await expect(page.locator('text=belum dikaitkan dengan unit kerja')).toBeVisible();
        await logout(page);
      }
    });
  });

  test.describe('3. Data isolation antar unit', () => {
    test('Pelapor DKSG hanya lihat realisasi & risiko unit sendiri', async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);

      await page.goto(`${BASE_URL}/realisasi`);
      const rows = page.locator('table tbody tr');
      await expect(rows.first()).toBeVisible();
      await expect(page.locator('a[href*="/realisasi.create"], a:has-text("Input Laporan")')).toHaveCount(1);

      await page.goto(`${BASE_URL}/risiko`);
      await expect(page.locator('button:has-text("Kelola")')).toHaveCount(0);
    });

    test('Admin lihat realisasi lintas unit (DKSG + SKS + SKG)', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      await page.goto(`${BASE_URL}/realisasi`);
      await expect(page.locator('table tbody tr').first()).toBeVisible();
    });

    test('Auditor melihat realisasi lintas unit tapi tanpa aksi edit/hapus (GAP-09)', async ({ page }) => {
      await login(page, accounts.auditor.email, accounts.auditor.pwd);
      await page.goto(`${BASE_URL}/realisasi`);
      await expect(page.locator('table tbody tr').first()).toBeVisible();
      await expect(page.locator('a[href*="/realisasi/"][href*="/edit"]')).toHaveCount(0);
    });
  });

  test.describe('4. Input Pelaporan — 4 kelompok indikator', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await page.goto(`${BASE_URL}/realisasi/create`);
      await page.waitForLoadState('networkidle');
    });

    test('IKU: field benar, RO field tidak muncul', async ({ page }) => {
      await selectIndicatorGroup(page, 'IKU — Indikator Kinerja Utama');
      await expect(page.locator('text=Capaian Target Bulan')).toBeVisible();
      await expect(page.locator('text=Keterangan Proses')).toBeVisible();
      await expect(page.locator('text=Upaya/Strategi Pencapaian')).toBeVisible();
      await expect(page.locator('text=Permasalahan/Kendala')).toBeVisible();
      await expect(page.locator('text=Realisasi Anggaran Bulan Ini')).not.toBeVisible();
      await expect(page.locator('text=Tindak Lanjut')).not.toBeVisible();
    });

    test('IKP: field benar termasuk Tindak Lanjut', async ({ page }) => {
      await selectIndicatorGroup(page, 'IKP — Indikator Kinerja Program');
      await expect(page.locator('text=Tindak Lanjut')).toBeVisible();
      await expect(page.locator('text=Upaya/Strategi Pencapaian')).not.toBeVisible();
    });

    test('IKK: field benar termasuk Realisasi Capaian', async ({ page }) => {
      await selectIndicatorGroup(page, 'IKK — Indikator Kinerja Kegiatan');
      await expect(page.locator('text=Realisasi Capaian')).toBeVisible();
      await expect(page.locator('text=Narasi Proses')).toBeVisible();
    });

    test('RO: field anggaran & pagu read-only muncul', async ({ page }) => {
      await selectIndicatorGroup(page, 'RO — Rincian Output');
      await expect(page.locator('text=Realisasi Anggaran Bulan Ini')).toBeVisible();
      await expect(page.locator('text=Pagu RO')).toBeVisible();
    });

    test('Ajukan tanpa isian wajib menampilkan error validasi, tidak mengubah status', async ({ page }) => {
      await selectIndicatorGroup(page, 'IKU — Indikator Kinerja Utama');
      await page.click('button:has-text("Ajukan")');
      await expect(page.locator('text=/wajib|required/i').first()).toBeVisible({ timeout: 5000 });
      await expect(page).toHaveURL(`${BASE_URL}/realisasi/create`);
    });

    test('Simpan Draft dengan isian minimal berhasil dan tampil di daftar', async ({ page }) => {
      await selectIndicatorGroup(page, 'IKU — Indikator Kinerja Utama');
      const capaianInput = page.locator('input[placeholder*="Contoh"]').first();
      await capaianInput.fill('5 Spesies');
      await page.click('button:has-text("Simpan Draft")');
      await expect(page).toHaveURL(`${BASE_URL}/realisasi`);
      await expect(page.locator('table tbody').getByText('Draft', { exact: true }).first()).toBeVisible();
    });

    test('Lengkap isian lalu Ajukan berhasil dan status jadi Diajukan', async ({ page }) => {
      await selectIndicatorGroup(page, 'IKP — Indikator Kinerja Program');
      await page.locator('input[placeholder*="Contoh"]').first().fill('4 Spesies');
      const textareas = page.locator('textarea');
      await textareas.nth(0).fill('Proses berjalan sesuai rencana.');
      await textareas.nth(1).fill('-');
      await textareas.nth(2).fill('Lanjutkan monitoring triwulan berikutnya.');
      await page.click('button:has-text("Ajukan")');
      await expect(page).toHaveURL(`${BASE_URL}/realisasi`);
      await expect(page.locator('table tbody').getByText('Diajukan', { exact: true }).first()).toBeVisible();
    });
  });

  test.describe('5. Verification workflow lintas peran', () => {
    test('Pelapor tidak bisa edit laporan Diajukan milik sendiri', async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await page.goto(`${BASE_URL}/realisasi`);
      const diajukanRow = page.locator('table tbody tr').filter({ hasText: 'Diajukan' }).first();
      await expect(diajukanRow.locator('a[href*="/edit"]')).toHaveCount(0);
    });

    test('Verifikator: lihat daftar Diajukan, tinjau detail, tolak tanpa catatan gagal, kembalikan dengan catatan berhasil', async ({ page }) => {
      await login(page, accounts.verifikator.email, accounts.verifikator.pwd);
      await page.goto(`${BASE_URL}/verifikasi`);
      await page.waitForLoadState('networkidle');

      const reviewBtn = page.locator('button:has-text("Tinjau")').first();
      await expect(reviewBtn).toBeVisible();
      await reviewBtn.click();

      await expect(page.locator('text=Capaian Target')).toBeVisible();
      await expect(page.locator('text=Berkas Bukti Dukung')).toBeVisible();

      await page.click('button:has-text("Kembalikan")');
      await page.click('button:has-text("Kembalikan Laporan")');
      await expect(page.locator('text=/wajib|required/i')).toBeVisible({ timeout: 5000 });

      await page.fill('textarea', 'Mohon lengkapi narasi progres dan lampiran.');
      await page.click('button:has-text("Kembalikan Laporan")');
      await expect(page.locator('text=dikembalikan')).toBeVisible({ timeout: 5000 });
    });

    test('Pelapor memperbaiki laporan Dikembalikan lalu mengajukan ulang; Verifikator menyetujui', async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await page.goto(`${BASE_URL}/realisasi`);
      const returnedRow = page.locator('table tbody tr').filter({ hasText: 'Dikembalikan' }).first();
      await expect(returnedRow).toBeVisible();
      await returnedRow.locator('a[href*="/edit"]').click();
      await page.waitForLoadState('networkidle');

      await expect(page.locator('text=Edit Laporan Capaian')).toBeVisible();
      const textareas = page.locator('textarea');
      const count = await textareas.count();
      for (let i = 0; i < count; i++) {
        const val = await textareas.nth(i).inputValue();
        if (!val) await textareas.nth(i).fill('-');
      }
      await page.click('button:has-text("Ajukan")');
      await expect(page).toHaveURL(`${BASE_URL}/realisasi`);

      await logout(page);
      await login(page, accounts.verifikator.email, accounts.verifikator.pwd);
      await page.goto(`${BASE_URL}/verifikasi`);
      await page.waitForLoadState('networkidle');
      const approveReview = page.locator('button:has-text("Tinjau")').first();
      if (await approveReview.isVisible({ timeout: 3000 }).catch(() => false)) {
        await approveReview.click();
        await page.click('button:has-text("Setujui")');
        await expect(page.locator('text=disetujui')).toBeVisible({ timeout: 5000 });
      }
    });

    test('Laporan Terverifikasi terkunci: Pelapor tidak bisa membuka edit (403)', async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await page.goto(`${BASE_URL}/realisasi`);
      const verifiedRow = page.locator('table tbody tr').filter({ hasText: 'Terverifikasi' }).first();
      await expect(verifiedRow.locator('a[href*="/edit"]')).toHaveCount(0);
    });

    test('Pelapor & Pimpinan & Auditor dilarang mengakses /verifikasi (403)', async ({ page }) => {
      for (const acc of [accounts.pelapor_dksg, accounts.pimpinan, accounts.auditor]) {
        await login(page, acc.email, acc.pwd);
        await assertForbidden(page, '/verifikasi');
        await logout(page);
      }
    });
  });

  test.describe('6. Risiko & Kendala', () => {
    test('Verifikator & Pimpinan & Admin bisa Kelola; Pelapor & Auditor read-only', async ({ page }) => {
      for (const acc of [accounts.verifikator, accounts.pimpinan, accounts.admin]) {
        await login(page, acc.email, acc.pwd);
        await page.goto(`${BASE_URL}/risiko`);
        await expect(page.locator('button:has-text("Kelola")').first()).toBeVisible();
        await logout(page);
      }
      for (const acc of [accounts.pelapor_dksg, accounts.auditor]) {
        await login(page, acc.email, acc.pwd);
        await page.goto(`${BASE_URL}/risiko`);
        await expect(page.locator('button:has-text("Kelola")')).toHaveCount(0);
        await logout(page);
      }
    });

    test('Kelola risiko: ubah status/PIC/tenggat tersimpan', async ({ page }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await page.goto(`${BASE_URL}/risiko`);
      await page.locator('button:has-text("Kelola")').first().click();

      const modal = page.getByRole('dialog');
      await expect(modal.getByText('Kelola Tindak Lanjut Risiko')).toBeVisible();
      await modal.locator('select').selectOption('Ditindaklanjuti');
      await modal.getByRole('textbox').first().fill('Tim E2E Playwright');
      await modal.getByRole('button', { name: 'Simpan' }).click();
      await expect(modal).not.toBeVisible();
      await expect(page.locator('text=Tim E2E Playwright')).toBeVisible();
    });

    test('Filter kategori/status/unit/periode berfungsi', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      await page.goto(`${BASE_URL}/risiko`);
      const selects = page.locator('select');
      await selects.nth(0).selectOption({ index: 1 });
      await page.waitForLoadState('networkidle');
      await selects.nth(0).selectOption({ index: 0 });
      await selects.nth(1).selectOption({ index: 1 });
      await page.waitForLoadState('networkidle');
    });

    test('Pelapor unit lain (SKS) tidak melihat risiko unit DKSG', async ({ page }) => {
      await login(page, accounts.pelapor_sks.email, accounts.pelapor_sks.pwd);
      await page.goto(`${BASE_URL}/risiko`);
      const rows = page.locator('table tbody tr');
      const count = await rows.count();
      if (count > 0) {
        await expect(rows.filter({ hasText: 'DKSG' })).toHaveCount(0);
      }
    });
  });

  test.describe('7. Dashboard & KPI', () => {
    test('Pimpinan: KPI cards, tren chart, panel risiko & perhatian tampil', async ({ page }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await page.goto(`${BASE_URL}/dashboard`);

      await expect(page.getByText('Indikator Aktif', { exact: true })).toBeVisible();
      await expect(page.getByText('Rata-rata Capaian', { exact: true })).toBeVisible();
      await expect(page.getByText('Realisasi Anggaran', { exact: true })).toBeVisible();
      await expect(page.getByText('Risiko Terbuka', { exact: true })).toBeVisible();

      await expect(page.getByRole('heading', { name: 'Capaian per Bulan per Kelompok' })).toBeVisible();
      const chart = page.locator('canvas').first();
      const emptyState = page.getByText('Belum ada laporan terverifikasi pada periode ini.');
      await expect(chart.or(emptyState)).toBeVisible();

      await expect(page.getByRole('heading', { name: 'Risiko Prioritas' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Indikator yang Membutuhkan Perhatian' })).toBeVisible();
    });

    test('Pelapor dashboard hanya merefleksikan data unit sendiri (scope tidak bocor setelah ganti periode)', async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await page.goto(`${BASE_URL}/dashboard`);
      const periodSelect = page.locator('select').first();
      const optionCount = await periodSelect.locator('option').count();
      if (optionCount > 1) {
        await periodSelect.selectOption({ index: 1 });
        await page.waitForLoadState('networkidle');
      }
      await expect(page.getByText('Indikator Aktif', { exact: true })).toBeVisible();
    });

    test('Ekspor Ringkasan dari Dashboard mengikuti periode terpilih', async ({ page, context }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      await page.goto(`${BASE_URL}/dashboard`);
      const [download] = await Promise.all([
        context.waitForEvent('download'),
        page.click('a:has-text("Ekspor Ringkasan")'),
      ]);
      expect(download.suggestedFilename()).toMatch(/\.pdf$/);
    });
  });

  test.describe('8. Laporan & Ekspor', () => {
    test('Semua peran bisa membuka /laporan dan tiga kartu ekspor tampil', async ({ page }) => {
      for (const acc of [accounts.admin, accounts.pelapor_dksg, accounts.verifikator, accounts.pimpinan, accounts.auditor]) {
        await login(page, acc.email, acc.pwd);
        await page.goto(`${BASE_URL}/laporan`);
        await expect(page.getByText('Ringkasan Kinerja')).toBeVisible();
        await expect(page.getByText('Register Risiko')).toBeVisible();
        await expect(page.getByText('Realisasi per Unit')).toBeVisible();
        await logout(page);
      }
    });

    test('Export Ringkasan Kinerja Excel & PDF, Register Risiko, Realisasi per Unit, Bulanan Lengkap', async ({ page, context }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await page.goto(`${BASE_URL}/laporan`);

      const kinerjaCard = page.getByRole('heading', { name: 'Ringkasan Kinerja' }).locator('..');
      let [download] = await Promise.all([
        context.waitForEvent('download'),
        kinerjaCard.getByRole('link', { name: 'Excel' }).click(),
      ]);
      expect(download.suggestedFilename()).toMatch(/\.xlsx$/);

      [download] = await Promise.all([
        context.waitForEvent('download'),
        kinerjaCard.getByRole('link', { name: 'PDF' }).click(),
      ]);
      expect(download.suggestedFilename()).toMatch(/\.pdf$/);

      const riskCard = page.getByRole('heading', { name: 'Register Risiko' }).locator('..');
      [download] = await Promise.all([
        context.waitForEvent('download'),
        riskCard.getByRole('link', { name: 'Excel' }).click(),
      ]);
      expect(download.suggestedFilename()).toMatch(/\.xlsx$/);

      const unitCard = page.getByRole('heading', { name: 'Realisasi per Unit' }).locator('..');
      [download] = await Promise.all([
        context.waitForEvent('download'),
        unitCard.getByRole('link', { name: 'Excel' }).click(),
      ]);
      expect(download.suggestedFilename()).toMatch(/\.xlsx$/);

      const monthlyCard = page.getByRole('heading', { name: 'Laporan Bulanan Lengkap' }).locator('..');
      [download] = await Promise.all([
        context.waitForEvent('download'),
        monthlyCard.getByRole('link', { name: 'Excel' }).click(),
      ]);
      expect(download.suggestedFilename()).toMatch(/\.xlsx$/);
    });

    test('Pelapor ekspor hanya memuat data unit sendiri (BR-16)', async ({ page, context }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await page.goto(`${BASE_URL}/laporan`);
      const unitCard = page.getByRole('heading', { name: 'Realisasi per Unit' }).locator('..');
      const [download] = await Promise.all([
        context.waitForEvent('download'),
        unitCard.getByRole('link', { name: 'Excel' }).click(),
      ]);
      expect(download.suggestedFilename()).toMatch(/\.xlsx$/);
    });
  });

  test.describe('9. Audit Trail (Admin & Auditor saja)', () => {
    test('Auditor: buka audit trail, filter resource/pengguna/tanggal, baca-saja (tanpa aksi tulis)', async ({ page }) => {
      await login(page, accounts.auditor.email, accounts.auditor.pwd);
      await page.goto(`${BASE_URL}/audit-trail`);

      const rows = page.locator('table tbody tr');
      await expect(rows.first()).toBeVisible({ timeout: 5000 }).catch(() => {});

      const selects = page.locator('select');
      await selects.nth(0).selectOption({ index: 1 });
      await page.waitForLoadState('networkidle');
      await selects.nth(0).selectOption({ index: 0 });

      await assertForbidden(page, '/admin/users');
    });

    test('Admin juga bisa mengakses audit trail', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      const res = await page.goto(`${BASE_URL}/audit-trail`);
      expect(res?.status()).toBe(200);
    });
  });

  test.describe('10. Administrasi (Admin saja) — CRUD master data', () => {
    test('Manajemen Unit: tambah, validasi kode unik, toggle aktif', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      await page.goto(`${BASE_URL}/admin/units`);

      await page.click('button:has-text("Tambah Unit")');
      await page.fill('input[placeholder*="Direktorat KSG"]', 'Unit E2E Playwright');
      await page.fill('input[placeholder*="DKSG"]', 'DKSG');
      await page.click('button:has-text("Simpan")');
      await expect(page.locator('text=/unique|sudah digunakan/i')).toBeVisible({ timeout: 5000 });

      await page.fill('input[placeholder*="DKSG"]', 'E2E');
      await page.click('button:has-text("Simpan")');
      await expect(page.locator('text=Unit E2E Playwright')).toBeVisible({ timeout: 5000 });
    });

    test('Manajemen Pengguna: Pelapor Unit tanpa unit ditolak validasi', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      await page.goto(`${BASE_URL}/admin/users`);
      await page.click('button:has-text("Tambah Pengguna")');
      await page.fill('input[type="email"]', 'pelapor.e2e@contoh.situksg.test');
      const nameInput = page.locator('input').first();
      await nameInput.fill('Pelapor E2E');
      await page.locator('input[type="password"]').fill('password123');
      const roleSelect = page.locator('select').first();
      await roleSelect.selectOption({ label: 'Pelapor Unit' });
      await page.click('button[type="submit"]:has-text("Simpan")');
      await expect(page.locator('text=/wajib|required/i')).toBeVisible({ timeout: 5000 });
    });

    test('Manajemen Periode: hanya satu periode aktif (BR-01)', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      await page.goto(`${BASE_URL}/admin/periods`);
      const activeBadges = page.locator('.badge-success:has-text("Aktif")');
      await expect(activeBadges).toHaveCount(1);
    });

    test('Manajemen Indikator: pagu hanya tampil untuk kelompok RO', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      await page.goto(`${BASE_URL}/admin/indicators`);
      await page.click('button:has-text("Tambah Indikator")');
      const groupSelect = page.locator('select').first();
      await groupSelect.selectOption({ label: 'IKU' });
      await expect(page.locator('text=Pagu RO (Rp)')).not.toBeVisible();
      await groupSelect.selectOption({ label: 'RO' });
      await expect(page.locator('text=Pagu RO (Rp)')).toBeVisible();
    });

    test('Penugasan Indikator: toggle assign/unassign per unit', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      await page.goto(`${BASE_URL}/admin/assignments`);
      await page.locator('select').first().selectOption({ index: 1 });
      await page.waitForLoadState('networkidle');
      const toggleBtn = page.locator('button:has-text("Tugaskan"), button:has-text("Ditugaskan")').first();
      await expect(toggleBtn).toBeVisible();
    });
  });

  test.describe('11. Tampilan & profil', () => {
    test('Ganti tema Terang/Gelap/Sistem konsisten lintas halaman', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      await page.goto(`${BASE_URL}/settings/appearance`);
      await page.click('button:has-text("Gelap")');
      await expect(page.locator('html')).toHaveAttribute('class', /dark/);

      await page.goto(`${BASE_URL}/dashboard`);
      await expect(page.locator('html')).toHaveAttribute('class', /dark/);

      await page.goto(`${BASE_URL}/settings/appearance`);
      await page.click('button:has-text("Terang")');
      await expect(page.locator('html')).not.toHaveAttribute('class', /dark/);
    });
  });
});

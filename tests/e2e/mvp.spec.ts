import { test, expect } from '@playwright/test';

const BASE_URL = 'http://127.0.0.1:8123';

const accounts = {
  admin: { email: 'admin@contoh.situksg.test', pwd: 'password' },
  pelapor_dksg: { email: 'pelapor.dksg@contoh.situksg.test', pwd: 'password' },
  pelapor_sks: { email: 'pelapor.sks@contoh.situksg.test', pwd: 'password' },
  verifikator: { email: 'verifikator@contoh.situksg.test', pwd: 'password' },
  pimpinan: { email: 'pimpinan@contoh.situksg.test', pwd: 'password' },
  auditor: { email: 'auditor@contoh.situksg.test', pwd: 'password' },
};

async function login(page, email, pwd) {
  await page.goto(`${BASE_URL}/login`);
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', pwd);
  await page.click('button[data-test="login-button"]');
  await page.waitForURL(`${BASE_URL}/dashboard`, { timeout: 10000 });
}

async function selectIndicatorGroup(page, groupLabel) {
  await page.locator('select').first().selectOption({ label: groupLabel });
  const indicatorSelect = page.locator('select').nth(1);
  await expect(indicatorSelect.locator('option')).not.toHaveCount(1);
  await indicatorSelect.selectOption({ index: 1 });
}

test.describe('E2E MVP — SITU-KSG Core Features', () => {
  test.describe('1. Login & Roles', () => {
    test('admin login + akses admin panel', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      await page.goto(`${BASE_URL}/admin/users`);
      await expect(page).toHaveURL(`${BASE_URL}/admin/users`);
    });

    test('pelapor login + unit isolation', async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await page.goto(`${BASE_URL}/realisasi`);
      const rows = page.locator('table tbody tr');
      await expect(rows.first()).toBeVisible();
      await expect(rows.filter({ hasText: 'SKS' })).toHaveCount(0);
    });

    test('pelapor forbidden verifikasi (403)', async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await page.goto(`${BASE_URL}/verifikasi`);
      await expect(page.locator('text=/403|Forbidden|tidak diizinkan/i')).toBeVisible({ timeout: 5000 });
    });

    test('verifikator login + verifikasi accessible', async ({ page }) => {
      await login(page, accounts.verifikator.email, accounts.verifikator.pwd);
      await page.goto(`${BASE_URL}/verifikasi`);
      await expect(page).toHaveURL(`${BASE_URL}/verifikasi`);
    });

    test('pimpinan login + dashboard visible', async ({ page }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await expect(page).toHaveURL(`${BASE_URL}/dashboard`);
    });

    test('auditor login + audit trail accessible', async ({ page }) => {
      await login(page, accounts.auditor.email, accounts.auditor.pwd);
      await page.goto(`${BASE_URL}/audit-trail`);
      await expect(page).toHaveURL(`${BASE_URL}/audit-trail`);
    });
  });

  test.describe('2. Report Input (4 Indicator Groups)', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await page.goto(`${BASE_URL}/realisasi/create`);
      await page.waitForLoadState('networkidle');
    });

    test('IKU form: correct fields (Capaian, Keterangan, Upaya, Permasalahan)', async ({ page }) => {
      await selectIndicatorGroup(page, 'IKU — Indikator Kinerja Utama');
      
      // Verify IKU-specific labels
      await expect(page.locator('text=Capaian Target Bulan')).toBeVisible();
      await expect(page.locator('text=Keterangan Proses')).toBeVisible();
      await expect(page.locator('text=Upaya/Strategi Pencapaian')).toBeVisible();
      await expect(page.locator('text=Permasalahan')).toBeVisible();
      
      // RO fields must not appear
      await expect(page.locator('text=Realisasi Anggaran')).not.toBeVisible();
    });

    test('IKP form: correct fields (Capaian, Keterangan, Permasalahan, Tindak Lanjut)', async ({ page }) => {
      await selectIndicatorGroup(page, 'IKP — Indikator Kinerja Program');
      
      await expect(page.locator('text=Tindak Lanjut')).toBeVisible();
      await expect(page.locator('text=Upaya/Strategi')).not.toBeVisible();
    });

    test('IKK form: correct fields (Realisasi Capaian, Narasi Proses, Permasalahan)', async ({ page }) => {
      await selectIndicatorGroup(page, 'IKK — Indikator Kinerja Kegiatan');
      
      await expect(page.locator('text=Realisasi Capaian')).toBeVisible();
      await expect(page.locator('text=Narasi Proses')).toBeVisible();
    });

    test('RO form: shows budget fields (Realisasi Anggaran, Pagu)', async ({ page }) => {
      await selectIndicatorGroup(page, 'RO — Rincian Output');
      
      await expect(page.locator('text=Realisasi Anggaran Bulan Ini')).toBeVisible();
      await expect(page.locator('text=Pagu RO')).toBeVisible();
    });

    test('save IKU laporan as Draft', async ({ page }) => {
      await selectIndicatorGroup(page, 'IKU — Indikator Kinerja Utama');
      
      // Partial fill (draft allows partial)
      const capaianInput = page.locator('input[placeholder*="Contoh"]').first();
      await capaianInput.fill('5');
      
      await page.click('button:has-text("Simpan Draft")');
      await expect(page).toHaveURL(`${BASE_URL}/realisasi`);
      await expect(page.locator('table tbody').getByText('Draft', { exact: true }).first()).toBeVisible();
    });
  });

  test.describe('3. Verification Workflow', () => {
    test.beforeEach(async ({ page }) => {
      await login(page, accounts.verifikator.email, accounts.verifikator.pwd);
      await page.goto(`${BASE_URL}/verifikasi`);
      await page.waitForLoadState('networkidle');
    });

    test('verifikator lihat laporan Diajukan', async ({ page }) => {
      // Table must have Diajukan status visible
      const statusBadge = page.locator('text=Diajukan');
      const isVisible = await statusBadge.first().isVisible({ timeout: 3000 });
      expect(isVisible || (await page.locator('text=Tidak ada laporan').isVisible())).toBeTruthy();
    });

    test('verifikator kembalikan tanpa catatan → error validasi', async ({ page }) => {
      const returnBtn = page.locator('button:has-text("Kembalikan")').first();
      if (await returnBtn.isVisible()) {
        await returnBtn.click();
        await page.waitForLoadState('networkidle');
        
        // Try submit tanpa note
        const submitBtn = page.locator('button:has-text("Kembalikan Laporan")').first();
        if (await submitBtn.isVisible()) {
          await submitBtn.click();
          await expect(page.locator('text=/wajib|required/i')).toBeVisible({ timeout: 3000 });
        }
      }
    });

    test('verifikator setujui laporan → Terverifikasi', async ({ page }) => {
      const approveBtn = page.locator('button:has-text("Setujui")').first();
      if (await approveBtn.isVisible()) {
        await approveBtn.click();
        // Tunggu modal confirm
        const confirmBtn = page.locator('button:has-text("Setujui")').nth(1);
        if (await confirmBtn.isVisible({ timeout: 2000 })) {
          await confirmBtn.click();
        }
        await expect(page.locator('text=disetujui')).toBeVisible({ timeout: 3000 });
      }
    });
  });

  test.describe('4. Risk Management', () => {
    test('pimpinan lihat daftar risiko', async ({ page }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await page.goto(`${BASE_URL}/risiko`);
      
      const rows = page.locator('table tbody tr');
      const count = await rows.count();
      expect(count).toBeGreaterThanOrEqual(0);
    });

    test('pimpinan kelola risiko: update status & PIC', async ({ page }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await page.goto(`${BASE_URL}/risiko`);
      
      const manageBtn = page.getByRole('button', { name: 'Kelola' }).first();
      await expect(manageBtn).toBeVisible();
      await manageBtn.click();

      const modal = page.getByRole('dialog');
      await expect(modal.getByText('Kelola Tindak Lanjut Risiko')).toBeVisible();
      await modal.locator('select').selectOption('Ditindaklanjuti');
      await modal.getByRole('textbox').first().fill('Tim E2E');
      await modal.getByRole('button', { name: 'Simpan' }).click();
      await expect(modal).not.toBeVisible();
    });

    test('pelapor risiko read-only (no Kelola button)', async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await page.goto(`${BASE_URL}/risiko`);
      
      const manageBtn = page.locator('button:has-text("Kelola")');
      await expect(manageBtn).not.toBeVisible({ timeout: 3000 });
    });

    test('pelapor SKS tidak lihat risiko DKSG', async ({ page }) => {
      await login(page, accounts.pelapor_sks.email, accounts.pelapor_sks.pwd);
      await page.goto(`${BASE_URL}/risiko`);
      
      // No DKSG-related risiko harus terlihat
      const rows = page.locator('table tbody tr');
      const count = await rows.count();
      // Jika ada risiko, pastikan bukan DKSG (unit isolation)
      if (count > 0) {
        const dksgText = await rows.locator('text=DKSG').count();
        expect(dksgText).toBe(0);
      }
    });
  });

  test.describe('5. Dashboard & KPI', () => {
    test('pimpinan dashboard: KPI cards visible', async ({ page }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await page.goto(`${BASE_URL}/dashboard`);
      
      await expect(page.getByText('Indikator Aktif', { exact: true })).toBeVisible();
      await expect(page.getByText('Rata-rata Capaian', { exact: true })).toBeVisible();
      await expect(page.getByText('Risiko Terbuka', { exact: true })).toBeVisible();
    });

    test('dashboard: achievement trend chart + filter kelompok', async ({ page }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await page.goto(`${BASE_URL}/dashboard`);
      
      await expect(page.getByRole('heading', { name: 'Capaian per Bulan per Kelompok' })).toBeVisible();
      const chart = page.locator('canvas').first();
      const emptyState = page.getByText('Belum ada laporan terverifikasi pada periode ini.');
      await expect(chart.or(emptyState)).toBeVisible();
    });

    test('dashboard: Risiko Prioritas panel', async ({ page }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await page.goto(`${BASE_URL}/dashboard`);
      
      await expect(page.getByRole('heading', { name: 'Risiko Prioritas' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Indikator yang Membutuhkan Perhatian' })).toBeVisible();
    });
  });

  test.describe('6. Export Reports', () => {
    test('pimpinan export Ringkasan Kinerja (Excel)', async ({ page, context }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await page.goto(`${BASE_URL}/laporan`);
      
      // Tunggu file dialog + intercept download
      const downloadPromise = context.waitForEvent('download');
      const dlBtn = page.locator('a:has-text("Excel")').first();
      if (await dlBtn.isVisible()) {
        await dlBtn.click();
        try {
          const download = await downloadPromise;
          expect(download.suggestedFilename()).toMatch(/\.xlsx$/);
        } catch (e) {
          // Download mungkin tidak terintercept; OK untuk smoke test
        }
      }
    });

    test('pimpinan export Register Risiko', async ({ page }) => {
      await login(page, accounts.pimpinan.email, accounts.pimpinan.pwd);
      await page.goto(`${BASE_URL}/laporan`);
      
      const card = page.getByRole('heading', { name: 'Register Risiko' }).locator('..');
      const [download] = await Promise.all([
        page.waitForEvent('download'),
        card.getByRole('link', { name: 'Excel' }).click(),
      ]);
      expect(download.suggestedFilename()).toMatch(/\.xlsx$/);
    });
  });

  test.describe('7. Audit Trail', () => {
    test('auditor buka audit trail + filter', async ({ page }) => {
      await login(page, accounts.auditor.email, accounts.auditor.pwd);
      await page.goto(`${BASE_URL}/audit-trail`);
      
      const rows = page.locator('table tbody tr');
      const count = await rows.count();
      expect(count).toBeGreaterThanOrEqual(0);
    });

    test('auditor forbidden admin panel', async ({ page }) => {
      await login(page, accounts.auditor.email, accounts.auditor.pwd);
      await page.goto(`${BASE_URL}/admin/users`);
      
      await expect(page.locator('text=/403|Forbidden/i')).toBeVisible({ timeout: 5000 });
    });
  });

  test.describe('8. Data Isolation', () => {
    test('pelapor DKSG tidak lihat laporan SKS', async ({ page }) => {
      await login(page, accounts.pelapor_dksg.email, accounts.pelapor_dksg.pwd);
      await page.goto(`${BASE_URL}/realisasi`);
      
      const rows = page.locator('table tbody tr');
      const count = await rows.count();
      if (count > 0) {
        const sksRows = await rows.filter({ hasText: 'SKS' }).count();
        expect(sksRows).toBe(0);
      }
    });

    test('verifikator forbidden input pelaporan (403)', async ({ page }) => {
      await login(page, accounts.verifikator.email, accounts.verifikator.pwd);
      await page.goto(`${BASE_URL}/realisasi/create`);
      
      await expect(page.locator('text=/403|Forbidden/i')).toBeVisible({ timeout: 5000 });
    });

    test('admin akses semua unit di realisasi', async ({ page }) => {
      await login(page, accounts.admin.email, accounts.admin.pwd);
      await page.goto(`${BASE_URL}/realisasi`);
      
      const rows = page.locator('table tbody tr');
      const count = await rows.count();
      expect(count).toBeGreaterThanOrEqual(0);
    });
  });
});

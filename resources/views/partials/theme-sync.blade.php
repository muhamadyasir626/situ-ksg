{{--
    Fase 16 — Mekanisme tema Light/Dark/System native MaryUI/daisyUI (Flux dilepas).

    Preferensi disimpan di localStorage (`appearance`: light|dark|system), default
    TERANG sampai pengguna memilih (§14.1). Mengatur baik atribut `data-theme`
    (situksg/situksg-dark, dibaca MaryUI/daisyUI) maupun class `.dark` (dibaca varian
    Tailwind `dark:` yang masih dipakai di sejumlah tempat) secara bersamaan.
    Berjalan sinkron di <head> agar tema terpasang sebelum body dirender (anti-FOUC).

    PENTING: `wire:navigate` (Livewire SPA navigation) merender ulang atribut <html>
    dari respons server — yang tidak pernah membawa data-theme/.dark — sehingga tema
    hasil JS terhapus pada setiap navigasi. Listener `livewire:navigated` di bawah
    menerapkan ulang tema setelah setiap swap halaman. Tanpa ini, komponen daisyUI
    dan utilitas `dark:` Tailwind lepas sinkron (sebagian gelap, sebagian terang).
--}}
<script>
    (function () {
        var LIGHT_THEME = 'situksg';
        var DARK_THEME = 'situksg-dark';
        var STORAGE_KEY = 'appearance';

        function systemPrefersDark() {
            return window.matchMedia('(prefers-color-scheme: dark)').matches;
        }

        function isDark(appearance) {
            return appearance === 'dark' || (appearance === 'system' && systemPrefersDark());
        }

        function apply(appearance) {
            var dark = isDark(appearance);
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.setAttribute('data-theme', dark ? DARK_THEME : LIGHT_THEME);
        }

        function applyStored() {
            apply(window.Appearance.get());
        }

        window.Appearance = {
            get: function () {
                return window.localStorage.getItem(STORAGE_KEY) || 'light';
            },
            set: function (value) {
                window.localStorage.setItem(STORAGE_KEY, value);
                apply(value);
            },
        };

        applyStored();

        if (! window.__appearanceListenersBound) {
            window.__appearanceListenersBound = true;

            document.addEventListener('livewire:navigated', applyStored);

            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                if (window.Appearance.get() === 'system') {
                    apply('system');
                }
            });
        }
    })();
</script>

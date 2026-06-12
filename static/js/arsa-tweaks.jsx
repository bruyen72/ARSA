/* ARSA — painel de Tweaks compartilhado.
   Cada página define window.ARSA_TWEAK_DEFAULTS num bloco EDITMODE
   antes de carregar este script. */

const ARSA_ACCENTS = {
  '#1b8fd9': { main: '#1b8fd9', deep: '#0f6fb0', soft: '#e3f1fb' }, // azul ARSA
  '#2a6fdb': { main: '#2a6fdb', deep: '#1c52ab', soft: '#e6eefb' }, // azul royal
  '#0f9b8e': { main: '#0f9b8e', deep: '#0a7268', soft: '#e0f4f2' }, // petróleo
};

function ArsaTweaksApp() {
  const defaults = Object.assign(
    { theme: 'claro', accentHex: '#1b8fd9', anim: true, radius: 14 },
    window.ARSA_TWEAK_DEFAULTS || {},
    window.__arsaSavedTweaks || {}
  );
  const [t, setTweak] = useTweaks(defaults);

  React.useEffect(() => {
    const accent = ARSA_ACCENTS[t.accentHex] || ARSA_ACCENTS['#1b8fd9'];
    const payload = { theme: t.theme, anim: t.anim, accent, radius: t.radius };
    if (window.__arsaApplyTweaks) window.__arsaApplyTweaks(payload);
    try {
      localStorage.setItem('arsa-tweaks-v1', JSON.stringify({
        theme: t.theme, accentHex: t.accentHex, anim: t.anim, radius: t.radius,
        accent,
      }));
    } catch (e) {}
  }, [t.theme, t.accentHex, t.anim, t.radius]);

  return (
    <TweaksPanel title="Tweaks">
      <TweakSection label="Variações de estilo" />
      <TweakRadio
        label="Estilo do site"
        value={t.theme}
        options={[
          { value: 'claro', label: 'Claro' },
          { value: 'profundo', label: 'Profundo' },
          { value: 'vibrante', label: 'Vibrante' },
        ]}
        onChange={(v) => setTweak('theme', v)}
      />
      <TweakColor
        label="Cor de destaque"
        value={t.accentHex}
        options={['#1b8fd9', '#2a6fdb', '#0f9b8e']}
        onChange={(v) => setTweak('accentHex', v)}
      />
      <TweakSection label="Detalhes" />
      <TweakSlider
        label="Cantos dos cartões"
        value={t.radius}
        min={4}
        max={24}
        unit="px"
        onChange={(v) => setTweak('radius', v)}
      />
      <TweakToggle
        label="Animações"
        value={t.anim}
        onChange={(v) => setTweak('anim', v)}
      />
    </TweaksPanel>
  );
}

(function () {
  const mount = document.createElement('div');
  mount.id = 'arsa-tweaks-root';
  document.body.appendChild(mount);
  ReactDOM.createRoot(mount).render(<ArsaTweaksApp />);
})();

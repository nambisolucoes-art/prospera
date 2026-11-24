import './Landing.css';

const root = document.body;
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const $ = <T extends HTMLElement>(selector: string, scope: ParentNode = document) => scope.querySelector<T>(selector);
const $$ = <T extends HTMLElement>(selector: string, scope: ParentNode = document) =>
  Array.from(scope.querySelectorAll<T>(selector));

root.classList.add('js');

/** Cabeçalho: fica sólido ao rolar e abre o menu no celular. */
function initHeader(): void {
  const header = $('[data-header]');
  const nav = $('[data-nav]');
  const toggle = $<HTMLButtonElement>('[data-nav-toggle]');
  if (!header || !nav || !toggle) return;

  const update = () => header.classList.toggle('is-solid', scrollY > 24);
  update();
  addEventListener('scroll', update, { passive: true });

  const setOpen = (open: boolean) => {
    nav.classList.toggle('is-open', open);
    header.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
  };
  toggle.addEventListener('click', () => setOpen(!nav.classList.contains('is-open')));
  nav.addEventListener('click', (event) => {
    if ((event.target as HTMLElement).closest('a')) setOpen(false);
  });
  addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });
}

/** Entrada dos blocos ao rolar e contadores dos números. */
function initReveal(): void {
  const items = $$('[data-reveal]');

  const count = (element: HTMLElement) => {
    const target = Number(element.dataset.count);
    if (reduced || !target) return;
    const start = performance.now();
    const step = (now: number) => {
      const progress = Math.min((now - start) / 1400, 1);
      element.textContent = String(Math.round(target * (1 - Math.pow(1 - progress, 3))));
      if (progress < 1) requestAnimationFrame(step);
    };
    element.textContent = '0';
    requestAnimationFrame(step);
  };

  if (!('IntersectionObserver' in window)) {
    items.forEach((item) => item.classList.add('is-in'));
    return;
  }
  const observer = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        const element = entry.target as HTMLElement;
        element.classList.add('is-in');
        element.querySelectorAll<HTMLElement>('[data-count]').forEach(count);
        observer.unobserve(element);
      }
    },
    { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
  );
  items.forEach((item) => observer.observe(item));
}

/** Abas de funcionalidades, com setas do teclado e abertura pelo diagrama. */
function initTabs(): void {
  const tabs = $('[data-tabs]');
  if (!tabs) return;
  const buttons = $$<HTMLButtonElement>('[data-tab]', tabs);
  const panels = $$('[role="tabpanel"]', tabs);

  const select = (id: string, focus = false) => {
    buttons.forEach((button) => {
      const active = button.dataset.tab === id;
      button.setAttribute('aria-selected', String(active));
      button.tabIndex = active ? 0 : -1;
      if (active && focus) button.focus();
    });
    panels.forEach((panel) => {
      panel.hidden = panel.id !== `panel-${id}`;
    });
  };

  buttons.forEach((button, index) => {
    button.addEventListener('click', () => select(button.dataset.tab ?? ''));
    button.addEventListener('keydown', (event) => {
      const move = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
      if (!move) return;
      event.preventDefault();
      select(buttons[(index + move + buttons.length) % buttons.length].dataset.tab ?? '', true);
    });
  });
  $$('[data-open-tab]').forEach((link) =>
    link.addEventListener('click', () => select(link.dataset.openTab ?? '')),
  );
}

/** Etapas de "Como funciona", com botões anterior e próxima. */
function initStepper(): void {
  const stepper = $('[data-stepper]');
  if (!stepper) return;
  const buttons = $$<HTMLButtonElement>('[data-step]', stepper);
  const cards = $$('[data-step-card]', stepper);
  let current = 0;

  const show = (index: number) => {
    current = (index + buttons.length) % buttons.length;
    buttons.forEach((button, position) => button.setAttribute('aria-selected', String(position === current)));
    cards.forEach((card, position) => {
      card.hidden = position !== current;
    });
  };
  buttons.forEach((button, index) => button.addEventListener('click', () => show(index)));
  $('[data-step-prev]', stepper)?.addEventListener('click', () => show(current - 1));
  $('[data-step-next]', stepper)?.addEventListener('click', () => show(current + 1));
}

/** Perguntas frequentes: uma resposta aberta por vez. */
function initAccordion(): void {
  const items = $$('[data-accordion] .lp-accordion__item');
  items.forEach((item) => {
    const button = $<HTMLButtonElement>('button[aria-expanded]', item);
    button?.addEventListener('click', () => {
      const open = item.classList.contains('is-open');
      items.forEach((other) => {
        other.classList.remove('is-open');
        $('button[aria-expanded]', other)?.setAttribute('aria-expanded', 'false');
      });
      if (open) return;
      item.classList.add('is-open');
      button.setAttribute('aria-expanded', 'true');
    });
  });
}

/**
 * Diagrama de módulos: os nós balançam e acompanham o mouse. Partículas fluem dos módulos para o núcleo e,
 * quando o mouse está sobre um módulo, o núcleo envia partículas na cor dele.
 */
function initHub(): void {
  const hub = $('[data-hub]');
  const canvas = $<HTMLCanvasElement>('[data-hub-canvas]');
  const core = $('[data-hub-core]');
  if (!hub || !canvas || !core || matchMedia('(max-width: 640px)').matches) return;
  const context = canvas.getContext('2d');
  if (!context) return;

  const nodes = $$('[data-hub-node]', hub);
  type Particle = { node: HTMLElement; outward: boolean; progress: number; speed: number; color: string };
  let particles: Particle[] = [];
  let hovered: HTMLElement | null = null;
  let visible = false;
  let frame = 0;
  let last = 0;
  let emitHot = 0;
  let emitAmbient = 0;

  const resize = () => {
    const box = hub.getBoundingClientRect();
    const ratio = Math.min(devicePixelRatio || 1, 2);
    canvas.width = box.width * ratio;
    canvas.height = box.height * ratio;
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
  };
  const center = (element: HTMLElement, origin: DOMRect) => {
    const target = $('.lp-hub__circle', element) ?? element;
    const box = target.getBoundingClientRect();
    return { x: box.left + box.width / 2 - origin.left, y: box.top + box.height / 2 - origin.top };
  };
  const spawn = (node: HTMLElement, outward: boolean) =>
    particles.push({
      node,
      outward,
      progress: 0,
      speed: outward ? 0.0016 + Math.random() * 0.0008 : 0.0007 + Math.random() * 0.0005,
      color: node.dataset.color ?? '#16a34a',
    });

  const draw = (time: number) => {
    const delta = Math.min(time - last, 50);
    last = time;
    const origin = hub.getBoundingClientRect();
    context.clearRect(0, 0, origin.width, origin.height);
    const from = center(core, origin);

    // Fluxo ambiente: um módulo aleatório envia uma partícula ao núcleo.
    emitAmbient += delta;
    if (emitAmbient > 380) {
      emitAmbient = 0;
      spawn(nodes[Math.floor(Math.random() * nodes.length)], false);
    }
    // Módulo sob o mouse: o núcleo dispara partículas em sequência para ele.
    emitHot += delta;
    if (hovered && emitHot > 110) {
      emitHot = 0;
      spawn(hovered, true);
    }

    particles = particles.filter((particle) => particle.progress < 1);
    for (const particle of particles) {
      particle.progress += particle.speed * delta;
      const to = center(particle.node, origin);
      const start = particle.outward ? from : to;
      const end = particle.outward ? to : from;
      const t = Math.min(particle.progress, 1);
      const x = start.x + (end.x - start.x) * t;
      const y = start.y + (end.y - start.y) * t;
      const tail = Math.max(t - 0.12, 0);
      const tx = start.x + (end.x - start.x) * tail;
      const ty = start.y + (end.y - start.y) * tail;
      const gradient = context.createLinearGradient(tx, ty, x, y);
      gradient.addColorStop(0, `${particle.color}00`);
      gradient.addColorStop(1, particle.color);
      context.strokeStyle = gradient;
      context.lineWidth = 2;
      context.lineCap = 'round';
      context.beginPath();
      context.moveTo(tx, ty);
      context.lineTo(x, y);
      context.stroke();
      context.fillStyle = particle.color;
      context.globalAlpha = particle.outward ? 1 : 0.8;
      context.beginPath();
      context.arc(x, y, particle.outward ? 3.4 : 2.6, 0, Math.PI * 2);
      context.fill();
      context.globalAlpha = 1;
    }
    if (visible) frame = requestAnimationFrame(draw);
  };

  const interactive = !reduced && matchMedia('(hover: hover)').matches;
  const shift = (event: PointerEvent) => {
    const box = hub.getBoundingClientRect();
    const x = (event.clientX - box.left) / box.width - 0.5;
    const y = (event.clientY - box.top) / box.height - 0.5;
    [core, ...nodes].forEach((element) => {
      const depth = Number(element.dataset.depth);
      element.style.translate = `${x * depth * 3}px ${y * depth * 3}px`;
    });
  };
  if (interactive) {
    hub.addEventListener('pointermove', shift);
    hub.addEventListener('pointerleave', () => [core, ...nodes].forEach((element) => (element.style.translate = '')));
  }
  nodes.forEach((node) => {
    const on = () => (hovered = node);
    const off = () => {
      if (hovered === node) hovered = null;
    };
    node.addEventListener('pointerenter', on);
    node.addEventListener('pointerleave', off);
    node.addEventListener('focus', on);
    node.addEventListener('blur', off);
  });

  resize();
  new ResizeObserver(resize).observe(hub);
  if (reduced) return;
  new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    cancelAnimationFrame(frame);
    if (visible) {
      last = performance.now();
      frame = requestAnimationFrame(draw);
    }
  }).observe(hub);
}

/** A demonstração ainda não tem destino: sem endereço configurado, o botão não navega. */
function initDemo(): void {
  $$<HTMLAnchorElement>('[data-demo]').forEach((link) =>
    link.addEventListener('click', (event) => {
      if (link.getAttribute('href') === '#') event.preventDefault();
    }),
  );
}

/** Profundidade do mockup do hero acompanhando o mouse. */
function initStage(): void {
  const stage = $('[data-stage]');
  if (!stage || reduced || !matchMedia('(hover: hover)').matches) return;
  const layers = $$('[data-depth]', stage);

  stage.addEventListener('pointermove', (event) => {
    const box = stage.getBoundingClientRect();
    const x = (event.clientX - box.left) / box.width - 0.5;
    const y = (event.clientY - box.top) / box.height - 0.5;
    layers.forEach((layer) => {
      const depth = Number(layer.dataset.depth);
      layer.style.translate = `${x * depth * 2}px ${y * depth * 2}px`;
    });
  });
  stage.addEventListener('pointerleave', () => layers.forEach((layer) => (layer.style.translate = '')));
}

/** Inclinação suave dos cartões de diferenciais. */
function initTilt(): void {
  if (reduced || !matchMedia('(hover: hover)').matches) return;
  $$('[data-tilt]').forEach((card) => {
    card.addEventListener('pointermove', (event) => {
      const box = card.getBoundingClientRect();
      const x = (event.clientX - box.left) / box.width - 0.5;
      const y = (event.clientY - box.top) / box.height - 0.5;
      card.style.transform = `perspective(700px) rotateX(${-y * 6}deg) rotateY(${x * 6}deg) translateY(-4px)`;
    });
    card.addEventListener('pointerleave', () => (card.style.transform = ''));
  });
}

/** Botões flutuantes: aparecem depois do hero. */
function initFloating(): void {
  const cta = $('[data-float-cta]');
  const top = $<HTMLButtonElement>('[data-top]');
  const update = () => {
    const visible = scrollY > innerHeight * 0.8;
    cta?.classList.toggle('is-visible', visible);
    top?.classList.toggle('is-visible', visible);
  };
  update();
  addEventListener('scroll', update, { passive: true });
  top?.addEventListener('click', () => scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' }));
}

initHeader();
initReveal();
initTabs();
initStepper();
initAccordion();
initHub();
initDemo();
initStage();
initTilt();
initFloating();

<!doctype html>
<html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="referrer" content="no-referrer">
        <meta name="description" content="Prospera: gestão empresarial para quem tem várias empresas, filiais e equipes. Estrutura organizacional, permissões por cargo, pessoas, clientes, fornecedores e auditoria de tudo o que muda.">
        <meta name="theme-color" content="#061f3f">
        <meta property="og:title" content="Prospera: gestão empresarial que acompanha a sua estrutura">
        <meta property="og:description" content="Uma conta, várias empresas, cada estabelecimento com seu contexto. Permissões por cargo e auditoria completa.">
        <meta property="og:type" content="website">
        <link rel="icon" type="image/svg+xml" href="/assets/brand/prospera-symbol.svg">
        <title>Prospera: gestão empresarial que acompanha a sua estrutura</title>
        @vite(['resources/js/pages/Landing/Landing.tsx'])
        @php
            $signedIn = auth()->check();
            $entryHref = $signedIn ? '/dashboard' : '/login';
            $entryLabel = $signedIn ? 'Ir para o painel' : 'Acessar o sistema';
            // Endereço da demonstração: sem valor, o botão fica sem destino até o agendamento existir.
            $demoHref = config('landing.demo_url') ?: '#';
            $tabs = [
                [
                    'id' => 'organizacao', 'icon' => 'ti-layout-grid2', 'label' => 'Organização',
                    'title' => 'Conta, empresas e estabelecimentos, do jeito que existem',
                    'text' => 'Cadastre a estrutura da sua operação uma vez: matriz, filiais, empresas do mesmo grupo e unidades. Cada nível tem identificação fiscal própria e um estabelecimento principal.',
                    'items' => ['Empresas com CNPJ numérico ou alfanumérico, razão social e inscrições', 'Matriz e filiais com classificação independente do estabelecimento principal', 'Área de trabalho: escolha em quais unidades trabalhar, sem trocar de sistema', 'Conta com uma empresa e uma unidade funciona sem seletor nem passo extra'],
                    'mock' => 'tree',
                ],
                [
                    'id' => 'pessoas', 'icon' => 'ti-id-badge', 'label' => 'Pessoas e relações',
                    'title' => 'Um cadastro de pessoa, várias relações comerciais',
                    'text' => 'CPF ou CNPJ, contatos e endereço ficam em um cadastro central. Cliente e Fornecedor são relações da pessoa com cada empresa, sem digitar tudo de novo.',
                    'items' => ['A mesma pessoa pode ser cliente de uma empresa e fornecedora de outra', 'Busca por CPF ou CNPJ antes de cadastrar, para não duplicar', 'Etiquetas coloridas por módulo para organizar a carteira', 'Contatos e dados da relação separados por empresa'],
                    'mock' => 'people',
                ],
                [
                    'id' => 'acessos', 'icon' => 'ti-key', 'label' => 'Acessos',
                    'title' => 'Cada pessoa vê e faz só o que o cargo permite',
                    'text' => 'Cargos com permissões por ação, atribuídos na conta, na empresa ou na unidade. O servidor confere cada operação, mesmo quando o botão está escondido.',
                    'items' => ['Permissões separadas: listar, visualizar, cadastrar, editar e excluir', 'Convite por e-mail e a mesma identidade operando em várias contas', 'Administradores locais gerenciam só o próprio escopo', 'Ninguém concede mais acesso do que possui'],
                    'mock' => 'roles',
                ],
                [
                    'id' => 'auditoria', 'icon' => 'ti-clipboard', 'label' => 'Auditoria',
                    'title' => 'Quem fez, quando fez e o que mudou',
                    'text' => 'Cada cadastro, alteração, exclusão e acesso importante deixa um registro com o antes e o depois, filtrado pelo seu escopo de acesso.',
                    'items' => ['Comparação de valores antigos e novos em cada alteração', 'Histórico protegido: ninguém edita ou apaga eventos', 'Senhas, tokens e imagens nunca entram no registro', 'Inspeção de suporte identificada com a marca própria'],
                    'mock' => 'audit',
                ],
                [
                    'id' => 'ajuda', 'icon' => 'ti-help-alt', 'label' => 'Ajuda na tela',
                    'title' => 'A equipe aprende usando, sem manual solto',
                    'text' => 'Cada tela tem um tutorial guiado que destaca os controles, e a Central de Ajuda explica o fluxo completo, respeitando o que o seu cargo pode acessar.',
                    'items' => ['Tutorial desta tela, com passos que demonstram filtros, colunas e ações', 'Central de Ajuda com busca e artigos por assunto', 'Conteúdo atualizado junto com cada mudança no sistema', 'Textos em português, com os mesmos nomes que aparecem na tela'],
                    'mock' => 'help',
                ],
            ];
            $roadmap = [
                ['icon' => 'ti-layout-grid2', 'name' => 'Organização', 'text' => 'Conta, empresas e estabelecimentos', 'done' => true],
                ['icon' => 'ti-id-badge', 'name' => 'Clientes e fornecedores', 'text' => 'Pessoas, relações e etiquetas', 'done' => true],
                ['icon' => 'ti-key', 'name' => 'Cargos e permissões', 'text' => 'Acesso por ação e por escopo', 'done' => true],
                ['icon' => 'ti-clipboard', 'name' => 'Auditoria', 'text' => 'Histórico completo e protegido', 'done' => true],
                ['icon' => 'ti-wallet', 'name' => 'Financeiro', 'text' => 'Contas a pagar e a receber', 'done' => false],
                ['icon' => 'ti-shopping-cart', 'name' => 'Compras e suprimentos', 'text' => 'Cotação, pedido e recebimento', 'done' => false],
                ['icon' => 'ti-package', 'name' => 'Estoque', 'text' => 'Saldos e movimentações por unidade', 'done' => false],
                ['icon' => 'ti-receipt', 'name' => 'Vendas e fiscal', 'text' => 'Pedidos, notas e apuração', 'done' => false],
                ['icon' => 'ti-pie-chart', 'name' => 'Relatórios', 'text' => 'Visão consolidada por empresa', 'done' => false],
            ];
            $steps = [
                ['icon' => 'ti-layers', 'title' => 'Contratação da conta', 'text' => 'A plataforma cria a conta do contratante e o primeiro administrador recebe o acesso inicial, com senha definida por link seguro.'],
                ['icon' => 'ti-layout-grid2', 'title' => 'Empresas e unidades', 'text' => 'O administrador cadastra as empresas, matriz e filiais. Toda empresa nasce com um estabelecimento principal ativo.'],
                ['icon' => 'ti-key', 'title' => 'Cargos e convites', 'text' => 'Crie cargos com as permissões de cada função e convide a equipe por e-mail. Cada pessoa aceita o convite com a própria identidade.'],
                ['icon' => 'ti-id-badge', 'title' => 'Operação diária', 'text' => 'A equipe cadastra clientes e fornecedores, escolhe a área de trabalho e opera só o que o cargo permite.'],
                ['icon' => 'ti-clipboard', 'title' => 'Acompanhamento', 'text' => 'A auditoria mostra quem mudou o quê. A Central de Ajuda e o tutorial de cada tela sustentam a equipe no dia a dia.'],
            ];
            $why = [
                ['icon' => 'ti-lock', 'title' => 'Dados isolados por conta', 'text' => 'Cada conta enxerga somente o que é dela. Um código de outra conta responde como se não existisse.'],
                ['icon' => 'ti-shield', 'title' => 'Autorização no servidor', 'text' => 'Esconder um botão nunca é a proteção. Cada rota e cada ação sensível é conferida antes de executar.'],
                ['icon' => 'ti-timer', 'title' => 'Feito para crescer', 'text' => 'Uma empresa e uma unidade hoje, várias empresas e filiais amanhã, sem refazer o cadastro nem os acessos.'],
                ['icon' => 'ti-headphone-alt', 'title' => 'Ajuda dentro do sistema', 'text' => 'Tutorial guiado e Central de Ajuda em português, atualizados junto com cada novidade.'],
            ];
            $faq = [
                ['Quais módulos já estão disponíveis?', 'Hoje o Prospera entrega a base do ERP: organização (conta, empresas e estabelecimentos), clientes e fornecedores, cargos e permissões, convites, auditoria, Central de Ajuda e tutoriais. Financeiro, compras, estoque, vendas e fiscal estão em construção e chegam por etapas.'],
                ['Posso ter várias empresas e filiais na mesma conta?', 'Sim. A conta reúne quantas empresas forem necessárias, e cada empresa tem seus estabelecimentos, com matriz, filiais e um estabelecimento principal. Quem tem só uma empresa e uma unidade usa o sistema sem seletor.'],
                ['Como funcionam as permissões?', 'Cada cargo reúne permissões por ação (listar, visualizar, cadastrar, editar, excluir e outras). O cargo pode ser atribuído na conta inteira, em uma empresa ou em uma unidade, e vale só ali. O servidor confere cada operação.'],
                ['Uma pessoa pode acessar mais de uma conta?', 'Sim. A mesma identidade de acesso pode receber convites de várias contas e escolher onde trabalhar. Cada conta mantém seus próprios cargos, dados e unidades, sem misturar informações.'],
                ['Tudo o que acontece fica registrado?', 'Cadastros, alterações, exclusões e acessos importantes entram na auditoria, com autor, data, escopo e o valor antes e depois. Senhas, tokens e imagens nunca são gravados no histórico.'],
            ];
        @endphp
    </head>
    <body class="lp">
        <a class="lp-skip" href="#conteudo">Ir para o conteúdo</a>

        <header class="lp-header" data-header>
            <div class="lp-container lp-header__bar">
                <a class="lp-header__brand" href="/" aria-label="Prospera, página inicial">
                    <img src="/assets/brand/prospera.svg" alt="Prospera" width="150" height="50">
                </a>
                <nav class="lp-nav" aria-label="Principal" data-nav>
                    <a href="#solucao">Solução</a>
                    <a href="#funcionalidades">Funcionalidades</a>
                    <a href="#roadmap">Módulos</a>
                    <a href="#como-funciona">Como funciona</a>
                    <a href="#faq">Dúvidas</a>
                    <a class="lp-nav__mobile" href="{{ $demoHref }}" data-demo>Agende sua demonstração</a>
                    <a class="lp-nav__mobile lp-nav__mobile--primary" href="{{ $entryHref }}">{{ $entryLabel }}</a>
                </nav>
                <a class="lp-btn lp-btn--outline-dark lp-header__demo" href="{{ $demoHref }}" data-demo>Agende sua demonstração</a>
                <a class="lp-btn lp-btn--primary lp-header__cta" href="{{ $entryHref }}">{{ $entryLabel }}</a>
                <button class="lp-header__toggle" type="button" aria-label="Abrir menu" aria-expanded="false" data-nav-toggle>
                    <i class="ti-menu" aria-hidden="true"></i>
                </button>
            </div>
        </header>

        <main id="conteudo">
            {{-- Hero --}}
            <section class="lp-hero" aria-labelledby="hero-title">
                <div class="lp-hero__blob lp-hero__blob--a" aria-hidden="true"></div>
                <div class="lp-hero__blob lp-hero__blob--b" aria-hidden="true"></div>
                <div class="lp-container lp-hero__grid">
                    <div class="lp-hero__copy">
                        <p class="lp-badge" data-reveal><span class="lp-badge__dot"></span> ERP em evolução, com novos módulos a caminho</p>
                        <h1 id="hero-title" data-reveal style="--d: 80ms">Gestão empresarial que <span class="lp-grad">acompanha a estrutura real</span> da sua empresa</h1>
                        <p class="lp-lead" data-reveal style="--d: 160ms">
                            Uma conta, várias empresas, cada estabelecimento com o seu contexto. Permissões por cargo,
                            histórico de tudo o que muda e uma ajuda que ensina na própria tela.
                        </p>
                        <div class="lp-actions" data-reveal style="--d: 240ms">
                            <a class="lp-btn lp-btn--primary lp-btn--lg" href="{{ $entryHref }}">{{ $entryLabel }} <i class="ti-arrow-right" aria-hidden="true"></i></a>
                            <a class="lp-btn lp-btn--ghost lp-btn--lg" href="{{ $demoHref }}" data-demo>Agende sua demonstração</a>
                        </div>
                        <ul class="lp-hero__points" data-reveal style="--d: 320ms">
                            <li><i class="ti-check" aria-hidden="true"></i> Multiempresa e multiestabelecimento</li>
                            <li><i class="ti-check" aria-hidden="true"></i> Permissões por ação e por escopo</li>
                            <li><i class="ti-check" aria-hidden="true"></i> Auditoria completa</li>
                        </ul>
                    </div>

                    <div class="lp-stage" aria-hidden="true" data-stage>
                        <div class="lp-window" data-depth="6">
                            <div class="lp-window__bar"><i></i><i></i><i></i><span>Organização</span></div>
                            <div class="lp-window__body">
                                <aside class="lp-window__side">
                                    <b><i class="ti-home"></i></b><b class="is-on"><i class="ti-layout-grid2"></i></b><b><i class="ti-id-badge"></i></b><b><i class="ti-key"></i></b><b><i class="ti-clipboard"></i></b>
                                </aside>
                                <div class="lp-window__main">
                                    <div class="lp-row lp-row--l0"><span class="lp-pill lp-pill--navy">Conta contratante</span><strong>Grupo Horizonte</strong></div>
                                    <div class="lp-row lp-row--l1"><span class="lp-pill lp-pill--blue">Empresa</span><strong>Horizonte Atacado</strong><em>Matriz</em></div>
                                    <div class="lp-row lp-row--l2"><span class="lp-pill lp-pill--green">Estabelecimento</span><strong>Centro de distribuição</strong><em class="is-ok">Principal</em></div>
                                    <div class="lp-row lp-row--l2"><span class="lp-pill lp-pill--green">Estabelecimento</span><strong>Loja Brumado</strong><em>Filial</em></div>
                                    <div class="lp-row lp-row--l1"><span class="lp-pill lp-pill--blue">Empresa</span><strong>Horizonte Logística</strong><em>Matriz</em></div>
                                    <div class="lp-row lp-row--l2"><span class="lp-pill lp-pill--green">Estabelecimento</span><strong>Base Guanambi</strong><em class="is-ok">Principal</em></div>
                                </div>
                            </div>
                        </div>
                        <div class="lp-float lp-float--a" data-depth="16">
                            <i class="ti-clipboard"></i>
                            <div><b>Auditoria</b><span>Filial cadastrada por Ana, agora</span></div>
                        </div>
                        <div class="lp-float lp-float--b" data-depth="-12">
                            <i class="ti-key"></i>
                            <div><b>Cargo: Gerente de unidade</b><span>Vale só em Loja Brumado</span></div>
                        </div>
                        <div class="lp-float lp-float--c" data-depth="10">
                            <span class="lp-tag" style="--c:#2266aa">Cliente VIP</span>
                            <span class="lp-tag" style="--c:#16a34a">Atacado</span>
                        </div>
                    </div>
                </div>
                <a class="lp-scroll" href="#solucao" aria-label="Rolar para a solução"><i class="ti-angle-down" aria-hidden="true"></i></a>
            </section>

            {{-- Letreiro --}}
            <div class="lp-marquee" aria-hidden="true">
                <div class="lp-marquee__track">
                    @foreach ([0, 1] as $copy)
                        <ul>
                            @foreach (['Multiempresa', 'Multiestabelecimento', 'Matriz e filial', 'Permissões por cargo', 'Auditoria', 'Convites', 'Clientes e fornecedores', 'Tutorial guiado', 'Várias contas', 'CNPJ alfanumérico'] as $word)
                                <li>{{ $word }}</li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </div>

            {{-- Números --}}
            <section class="lp-section lp-stats" aria-label="O Prospera em números">
                <div class="lp-container lp-stats__grid">
                    <div class="lp-stat" data-reveal><strong><span data-count="3">3</span></strong><span>níveis de organização: conta, empresa e estabelecimento</span></div>
                    <div class="lp-stat" data-reveal style="--d: 80ms"><strong><span data-count="100">100</span>%</strong><span>das operações de negócio auditadas</span></div>
                    <div class="lp-stat" data-reveal style="--d: 160ms"><strong><span data-count="1">1</span></strong><span>identidade de acesso para várias contas</span></div>
                    <div class="lp-stat" data-reveal style="--d: 240ms"><strong><span data-count="0">0</span></strong><span>dados compartilhados entre contas diferentes</span></div>
                </div>
            </section>

            {{-- Solução / hub --}}
            <section id="solucao" class="lp-section lp-solution" aria-labelledby="solucao-title">
                <div class="lp-container">
                    <header class="lp-heading" data-reveal>
                        <p class="lp-eyebrow">A proposta</p>
                        <h2 id="solucao-title">Módulos que se conectam com a gestão da sua empresa</h2>
                        <p>Antes de lançar notas e pedidos, é preciso saber quem é quem: qual empresa, qual unidade, qual cargo, qual pessoa. O Prospera resolve essa base primeiro e conecta cada módulo novo a ela.</p>
                    </header>

                    @php
                        $nodes = [
                            ['ti-layout-grid2', 'Organização', 'organizacao', 'done', '#16a34a'],
                            ['ti-id-badge', 'Pessoas', 'pessoas', 'done', '#0284c7'],
                            ['ti-key', 'Acessos', 'acessos', 'done', '#7c3aed'],
                            ['ti-clipboard', 'Auditoria', 'auditoria', 'done', '#db2777'],
                            ['ti-wallet', 'Financeiro', null, 'soon', '#14b8a6'],
                            ['ti-shopping-cart', 'Compras', null, 'soon', '#ef4444'],
                            ['ti-package', 'Estoque', null, 'soon', '#f59e0b'],
                            ['ti-receipt', 'Fiscal', null, 'soon', '#2e5cb8'],
                        ];
                    @endphp
                    <div class="lp-hub" data-hub data-reveal>
                        <svg class="lp-hub__arc" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="hub-arc" x1="0" x2="1" y1="0" y2="0">
                                    <stop offset="0" stop-color="#16a34a" /><stop offset="0.5" stop-color="#5d9bec" /><stop offset="1" stop-color="#95e000" />
                                </linearGradient>
                            </defs>
                            <path d="M 4 86 A 46 78 0 0 1 96 86" />
                        </svg>
                        <canvas class="lp-hub__canvas" aria-hidden="true" data-hub-canvas></canvas>
                        <div class="lp-hub__core" data-hub-core data-depth="-8" aria-hidden="true">
                            <span class="lp-hub__halo"></span>
                            <span class="lp-hub__disc"><img src="/assets/brand/prospera-symbol.svg" alt="" width="64" height="64"></span>
                        </div>
                        @foreach ($nodes as $i => [$icon, $label, $tab, $state, $color])
                            @php
                                $angle = M_PI + M_PI * ($i + 0.5) / count($nodes);
                                $x = 50 + 46 * cos($angle);
                                $y = 86 + 78 * sin($angle);
                            @endphp
                            <a class="lp-hub__node is-{{ $state }}" style="left: {{ round($x, 2) }}%; top: {{ round($y, 2) }}%; --i: {{ $i }}; --c: {{ $color }}"
                               data-hub-node data-depth="{{ 8 + ($i % 3) * 5 }}" data-color="{{ $color }}"
                               @if ($tab) href="#funcionalidades" data-open-tab="{{ $tab }}" @else href="#roadmap" @endif>
                                <span class="lp-hub__bob">
                                    <span class="lp-hub__circle"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                                    <span class="lp-hub__label">{{ $label }}@if ($state === 'soon')<small>Em construção</small>@endif</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Funcionalidades em abas --}}
            <section id="funcionalidades" class="lp-section lp-features" aria-labelledby="func-title">
                <div class="lp-container">
                    <header class="lp-heading lp-heading--light" data-reveal>
                        <p class="lp-eyebrow">O que já existe</p>
                        <h2 id="func-title">Funcionalidades prontas para a operação</h2>
                    </header>

                    <div class="lp-tabs" data-tabs>
                        <div class="lp-tabs__list" role="tablist" aria-label="Funcionalidades" data-reveal>
                            @foreach ($tabs as $i => $tab)
                                <button type="button" role="tab" id="tab-{{ $tab['id'] }}" aria-controls="panel-{{ $tab['id'] }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" tabindex="{{ $i === 0 ? '0' : '-1' }}" data-tab="{{ $tab['id'] }}">
                                    <i class="{{ $tab['icon'] }}" aria-hidden="true"></i><span>{{ $tab['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                        @foreach ($tabs as $i => $tab)
                            <div class="lp-tabs__panel" role="tabpanel" id="panel-{{ $tab['id'] }}" aria-labelledby="tab-{{ $tab['id'] }}" @if ($i) hidden @endif>
                                <div class="lp-tabs__copy">
                                    <h3>{{ $tab['title'] }}</h3>
                                    <p>{{ $tab['text'] }}</p>
                                    <ul class="lp-checks">
                                        @foreach ($tab['items'] as $item)
                                            <li><i class="ti-check" aria-hidden="true"></i><span>{{ $item }}</span></li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div class="lp-tabs__visual" aria-hidden="true">
                                    <div class="lp-panel">
                                        @switch($tab['mock'])
                                            @case('tree')
                                                <div class="lp-mock-title">Estrutura da conta</div>
                                                <div class="lp-row lp-row--l0"><span class="lp-pill lp-pill--navy">Conta</span><strong>Grupo Horizonte</strong></div>
                                                <div class="lp-row lp-row--l1"><span class="lp-pill lp-pill--blue">Empresa</span><strong>Horizonte Atacado</strong><em>12.345.678/0001-95</em></div>
                                                <div class="lp-row lp-row--l2"><span class="lp-pill lp-pill--green">Unidade</span><strong>Centro de distribuição</strong><em class="is-ok">Principal</em></div>
                                                <div class="lp-row lp-row--l2"><span class="lp-pill lp-pill--green">Unidade</span><strong>Loja Brumado</strong><em>Filial</em></div>
                                                <div class="lp-row lp-row--l1"><span class="lp-pill lp-pill--blue">Empresa</span><strong>Horizonte Logística</strong><em>G7RT4N2B/0001-xx</em></div>
                                                @break
                                            @case('people')
                                                <div class="lp-mock-title">Clientes</div>
                                                <div class="lp-table">
                                                    <div class="lp-table__head"><span>Nome</span><span>Empresa</span><span>Etiquetas</span></div>
                                                    <div><strong>Marta Queiroz</strong><span>Atacado</span><span><b class="lp-tag" style="--c:#2266aa">Cliente VIP</b></span></div>
                                                    <div><strong>Marta Queiroz</strong><span>Logística</span><span><b class="lp-tag" style="--c:#16a34a">Atacado</b></span></div>
                                                    <div><strong>Casa do Campo</strong><span>Atacado</span><span><b class="lp-tag" style="--c:#7c3aed">Prioritário</b></span></div>
                                                </div>
                                                <p class="lp-mock-note"><i class="ti-link"></i> Mesma pessoa, uma relação por empresa</p>
                                                @break
                                            @case('roles')
                                                <div class="lp-mock-title">Cargo: Gerente de unidade</div>
                                                <ul class="lp-perm">
                                                    <li class="on"><i class="ti-check"></i>Listar clientes</li>
                                                    <li class="on"><i class="ti-check"></i>Cadastrar clientes</li>
                                                    <li class="on"><i class="ti-check"></i>Editar clientes</li>
                                                    <li><i class="ti-close"></i>Excluir clientes</li>
                                                    <li><i class="ti-close"></i>Gerenciar cargos</li>
                                                </ul>
                                                <p class="lp-mock-note"><i class="ti-map-alt"></i> Atribuído em: Loja Brumado</p>
                                                @break
                                            @case('audit')
                                                <div class="lp-mock-title">Alteração de cliente</div>
                                                <div class="lp-diff"><span>Situação</span><del>Ativo</del><ins>Inativo</ins></div>
                                                <div class="lp-diff"><span>Etiqueta</span><del>Cliente</del><ins>Cliente VIP</ins></div>
                                                <p class="lp-mock-note"><i class="ti-user"></i> Ana · Loja Brumado · hoje, 10:42</p>
                                                @break
                                            @default
                                                <div class="lp-mock-title">Tutorial desta tela</div>
                                                <div class="lp-tour"><span class="lp-tour__spot"></span><div><b>Buscar e filtrar</b><p>A lista se atualiza ao digitar.</p><small>Passo 1 de 9</small></div></div>
                                                <p class="lp-mock-note"><i class="ti-book"></i> Central de Ajuda com busca</p>
                                        @endswitch
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Roadmap --}}
            <section id="roadmap" class="lp-section lp-roadmap" aria-labelledby="roadmap-title">
                <div class="lp-container">
                    <header class="lp-heading" data-reveal>
                        <p class="lp-eyebrow">Módulos</p>
                        <h2 id="roadmap-title">O que está pronto e o que vem a seguir</h2>
                        <p>Construímos por etapas e dizemos com clareza o que existe hoje. Os módulos em construção entram sobre a mesma base de contas, permissões e auditoria.</p>
                    </header>
                    <div class="lp-modules">
                        @foreach ($roadmap as $i => $module)
                            <article class="lp-module {{ $module['done'] ? 'is-done' : 'is-soon' }}" data-reveal style="--d: {{ ($i % 3) * 80 }}ms">
                                <i class="{{ $module['icon'] }}" aria-hidden="true"></i>
                                <div>
                                    <h3>{{ $module['name'] }}</h3>
                                    <p>{{ $module['text'] }}</p>
                                </div>
                                <span class="lp-status">{{ $module['done'] ? 'Disponível' : 'Em construção' }}</span>
                            </article>
                        @endforeach
                    </div>
                    <p class="lp-fineprint" data-reveal>A ordem e os prazos dos próximos módulos podem mudar conforme as prioridades dos clientes.</p>
                </div>
            </section>

            {{-- Como funciona --}}
            <section id="como-funciona" class="lp-section lp-how" aria-labelledby="como-title">
                <div class="lp-container">
                    <header class="lp-heading lp-heading--light" data-reveal>
                        <p class="lp-eyebrow">Como funciona</p>
                        <h2 id="como-title">Do primeiro acesso à operação, em cinco etapas</h2>
                    </header>
                    <div class="lp-stepper" data-stepper>
                        <ol class="lp-stepper__list" role="tablist" aria-label="Etapas">
                            @foreach ($steps as $i => $step)
                                <li>
                                    <button type="button" role="tab" id="step-{{ $i }}" aria-controls="step-panel" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" data-step="{{ $i }}">
                                        <b>{{ $i + 1 }}</b><span>{{ $step['title'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ol>
                        <div class="lp-stepper__stage" id="step-panel" role="tabpanel" aria-live="polite">
                            @foreach ($steps as $i => $step)
                                <div class="lp-stepper__card" data-step-card="{{ $i }}" @if ($i) hidden @endif>
                                    <i class="{{ $step['icon'] }}" aria-hidden="true"></i>
                                    <h3>{{ $step['title'] }}</h3>
                                    <p>{{ $step['text'] }}</p>
                                </div>
                            @endforeach
                            <div class="lp-stepper__nav">
                                <button type="button" class="lp-btn lp-btn--light" data-step-prev>Anterior</button>
                                <button type="button" class="lp-btn lp-btn--lime" data-step-next>Próxima etapa</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Por que --}}
            <section class="lp-section lp-why" aria-labelledby="why-title">
                <div class="lp-container">
                    <header class="lp-heading" data-reveal>
                        <p class="lp-eyebrow">Diferenciais</p>
                        <h2 id="why-title">Por que começar pela base certa</h2>
                    </header>
                    <div class="lp-cards">
                        @foreach ($why as $i => $card)
                            <article class="lp-card" data-reveal data-tilt style="--d: {{ $i * 80 }}ms">
                                <span class="lp-card__icon"><i class="{{ $card['icon'] }}" aria-hidden="true"></i></span>
                                <h3>{{ $card['title'] }}</h3>
                                <p>{{ $card['text'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- FAQ --}}
            <section id="faq" class="lp-section lp-faq" aria-labelledby="faq-title">
                <div class="lp-container lp-faq__grid">
                    <header class="lp-heading lp-heading--left" data-reveal>
                        <p class="lp-eyebrow">Dúvidas</p>
                        <h2 id="faq-title">Perguntas frequentes</h2>
                        <p>Se a sua dúvida não está aqui, fale com a equipe.</p>
                    </header>
                    <div class="lp-accordion" data-accordion>
                        @foreach ($faq as $i => [$question, $answer])
                            <div class="lp-accordion__item" data-reveal style="--d: {{ $i * 60 }}ms">
                                <h3>
                                    <button type="button" id="faq-q{{ $i }}" aria-expanded="false" aria-controls="faq-a{{ $i }}">
                                        <span>{{ $question }}</span><i class="ti-angle-down" aria-hidden="true"></i>
                                    </button>
                                </h3>
                                <div class="lp-accordion__answer" id="faq-a{{ $i }}" role="region" aria-labelledby="faq-q{{ $i }}">
                                    <div><p>{{ $answer }}</p></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- CTA final --}}
            <section id="contato" class="lp-cta" aria-labelledby="cta-title">
                <div class="lp-cta__glow" aria-hidden="true"></div>
                <div class="lp-container lp-cta__inner" data-reveal>
                    <h2 id="cta-title">Pronto para organizar a sua empresa de ponta a ponta?</h2>
                    <p>Comece pela estrutura, as pessoas e os acessos. Os módulos operacionais chegam sobre uma base que já funciona.</p>
                    <div class="lp-actions lp-actions--center">
                        <a class="lp-btn lp-btn--lime lp-btn--lg" href="{{ $entryHref }}">{{ $entryLabel }} <i class="ti-arrow-right" aria-hidden="true"></i></a>
                        <a class="lp-btn lp-btn--outline lp-btn--lg" href="{{ $demoHref }}" data-demo><i class="ti-agenda" aria-hidden="true"></i> Agende sua demonstração</a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="lp-footer">
            <div class="lp-container lp-footer__grid">
                <div>
                    <img src="/assets/brand/prospera-symbol.svg" alt="" width="36" height="36">
                    <b>Prospera</b>
                    <p>Gestão empresarial que acompanha a estrutura real da sua empresa.</p>
                </div>
                <nav aria-label="Rodapé">
                    <a href="#solucao">Solução</a>
                    <a href="#funcionalidades">Funcionalidades</a>
                    <a href="#roadmap">Módulos</a>
                    <a href="#faq">Dúvidas</a>
                    <a href="{{ $entryHref }}">{{ $signedIn ? 'Painel' : 'Entrar' }}</a>
                </nav>
            </div>
            <div class="lp-container lp-footer__legal">© {{ date('Y') }} Prospera. Todos os direitos reservados.</div>
        </footer>

        <a class="lp-float-cta" href="{{ $entryHref }}" data-float-cta>{{ $entryLabel }} <i class="ti-arrow-right" aria-hidden="true"></i></a>
        <button class="lp-top" type="button" aria-label="Voltar ao topo" data-top><i class="ti-arrow-up" aria-hidden="true"></i></button>
    </body>
</html>

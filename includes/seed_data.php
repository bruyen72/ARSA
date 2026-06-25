<?php
/**
 * Dados iniciais (seed) — usados apenas na primeira execução, quando o
 * banco local.db ainda não existe / está vazio. Depois disso, tudo passa
 * a ser editado pelo painel /admin.
 *
 * Migrado de assets/catalogo.js e do conteúdo fixo das páginas .html atuais.
 */

function seed_services(): array
{
    return [
        [
            'name' => 'Rádios prontos para a operação',
            'category' => 'Locação e venda',
            'description' => 'Soluções completas em radiocomunicação com rádios portáteis, móveis e repetidoras já configurados e preparados para entrar em operação.',
            'features' => [
                'Locação diária, quinzenal ou mensal',
                'Equipamentos já programados',
                'Manutenção e troca por nossa conta nos planos mensais',
            ],
            'image_path' => 'static/img/services/service-rental.webp',
        ],
        [
            'name' => 'Coleta, diagnóstico e reparo',
            'category' => 'Manutenção técnica',
            'description' => 'Da coleta à entrega, garantimos um serviço completo: avaliação em bancada, programação, testes de funcionamento e suporte técnico para manter sua operação conectada.',
            'features' => [
                'Coleta do equipamento',
                'Diagnóstico e reparo em laboratório',
                'Programação e testes antes da devolução',
            ],
            'image_path' => 'static/img/services/service-maintenance.webp',
        ],
        [
            'name' => 'Rede planejada e dimensionada para máxima eficiência operacional.',
            'category' => 'Consultoria para licenciamento junto à ANATEL',
            'description' => 'Visitamos sua operação, analisamos as necessidades de comunicação, planejamos a cobertura, configuramos canais e repetidoras e acompanhamos a regularização necessária para uma operação segura, eficiente e sem interrupções.',
            'features' => [
                'Visita técnica e planejamento de cobertura',
                'Definição de canais e repetição',
                'Licenciamento junto à ANATEL',
            ],
            'image_path' => 'static/img/services/service-consulting.webp',
        ],
    ];
}

function seed_content(): array
{
    return [
        // Home — hero
        'hero_eyebrow' => 'Radiocomunicação em Mato Grosso',
        'hero_title_pre' => 'Mais de 25 anos garantindo',
        'hero_title_destaque' => 'comunicação sem falhas',
        'hero_lead' => 'Mais do que oferecer equipamentos, entregamos suporte técnico e comunicação que funciona de verdade.',

        // Home — quem somos (resumo)
        'about_kicker' => 'Quem somos',
        'about_title' => 'Pioneiros em redes de comunicação no Mato Grosso',
        'about_text' => 'Ao longo de mais de duas décadas, ajudamos empresas de diversos segmentos a manter suas operações conectadas, organizadas e eficientes.',
        'about_text_extra' => 'Trabalhamos com locação de rádios nos formatos diário, quinzenal e mensal, oferecendo flexibilidade de acordo com a necessidade de cada cliente.',
        'about_foto' => 'static/img/empresa.jpeg',

        // Página Quem Somos (texto institucional completo)
        'quem_somos_titulo' => 'Quem somos',
        'quem_somos_texto' => "A ARSA é uma empresa de Cuiabá - MT especializada em radiocomunicação, com mais de 25 anos de experiência ajudando empresas de diversos segmentos a manter suas operações conectadas.\n\nTrabalhamos com locação e venda de rádios portáteis, móveis e repetidoras das marcas Caltta, Motorola, Intelbras e Hytera, além de oferecer manutenção técnica completa, consultoria e regularização junto à ANATEL.\n\nNossa missão é entregar comunicação que funciona de verdade: equipamentos confiáveis, suporte técnico próximo e flexibilidade para cada tipo de operação, do evento de fim de semana à fazenda inteira.",
        'quem_somos_foto' => 'static/img/empresa.jpeg',

        // Página Produtos
        'produtos_titulo' => 'Nossos produtos',
        'produtos_texto' => 'Rádios portáteis, móveis e repetidoras das principais marcas: Caltta, Motorola, Intelbras e Hytera.',

        // Garantia (página Serviços)
        'warranty_years' => '3',
        'warranty_title' => 'Garantia de até 3 anos para equipamentos novos.',
        'warranty_text' => 'Mais segurança para investir em rádios novos, com suporte técnico próximo e acompanhamento da ARSA depois da entrega.',

        // Contato / rodapé
        'contact_phone_display' => '(65) 8445-1909',
        'contact_whatsapp' => '556584451909',
        'contact_address_line1' => 'R. São Joaquim, 726 — Centro Sul',
        'contact_address_line2' => 'Cuiabá - MT, 78020-150',
        'contact_email' => '',
        'contact_hours' => 'Segunda a sexta, das 08h às 18h',

        // Redes sociais
        'social_instagram' => '',
        'social_facebook' => '',
        'social_linkedin' => '',

        // Rodapé
        'footer_copyright' => '© 2026 ARSA Radiocomunicação. Todos os direitos reservados.',
        'footer_location' => 'Cuiabá — Mato Grosso',

        // Categorias de produtos (JSON — gerenciadas em /admin/categorias)
        'product_categories' => json_encode([
            'portatil'   => 'Rádio Portátil',
            'movel'      => 'Rádio Móvel',
            'repetidora' => 'Repetidora',
        ], JSON_UNESCAPED_UNICODE),
    ];
}

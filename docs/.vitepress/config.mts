import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'SQL to Eloquent',
  description: 'Gera artefatos Laravel a partir de schemas SQL MySQL/MariaDB',
  base: '/sql-to-eloquent/',
  lang: 'pt-BR',
  cleanUrls: true,
  lastUpdated: true,

  themeConfig: {
    logo: undefined,
    siteTitle: 'SQL to Eloquent',
    nav: [
      { text: 'Guia', link: '/guia/instalacao' },
      { text: 'CLI', link: '/guia/cli' },
      {
        text: 'v1.0.1',
        items: [
          { text: 'Changelog', link: 'https://github.com/gui1535/sql-to-eloquent/releases' },
          { text: 'Packagist', link: 'https://packagist.org/packages/gui1535/sql-to-eloquent' },
        ],
      },
    ],

    sidebar: [
      {
        text: 'Introdução',
        items: [
          { text: 'Visão geral', link: '/' },
          { text: 'Instalação', link: '/guia/instalacao' },
        ],
      },
      {
        text: 'Uso',
        items: [
          { text: 'Primeiros passos', link: '/guia/uso' },
          { text: 'Opções da CLI', link: '/guia/cli' },
          { text: 'O que é gerado', link: '/guia/geracao' },
          { text: 'Configuração', link: '/guia/configuracao' },
        ],
      },
      {
        text: 'Referência',
        items: [
          { text: 'Suporte SQL', link: '/guia/suporte-sql' },
          { text: 'Limitações', link: '/guia/limitacoes' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/gui1535/sql-to-eloquent' },
    ],

    search: {
      provider: 'local',
      options: {
        translations: {
          button: {
            buttonText: 'Buscar',
            buttonAriaLabel: 'Buscar',
          },
          modal: {
            noResultsText: 'Nenhum resultado',
            resetButtonTitle: 'Limpar',
            footer: {
              selectText: 'selecionar',
              navigateText: 'navegar',
              closeText: 'fechar',
            },
          },
        },
      },
    },

    editLink: {
      pattern: 'https://github.com/gui1535/sql-to-eloquent/edit/main/docs/:path',
      text: 'Editar esta página',
    },

    footer: {
      message: 'Publicado sob licença MIT',
      copyright: 'Copyright © 2026 Guilherme',
    },

    outline: {
      label: 'Nesta página',
    },

    docFooter: {
      prev: 'Anterior',
      next: 'Próximo',
    },

    lastUpdated: {
      text: 'Atualizado em',
    },

    returnToTopLabel: 'Voltar ao topo',
    sidebarMenuLabel: 'Menu',
    darkModeSwitchLabel: 'Tema',
  },
})

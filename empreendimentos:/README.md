# Fontec Empreendimentos

## Estrutura de arquivos

```
empreendimentos/
├── index.php          → Página pública
├── admin.php          → Painel administrativo
├── .htaccess          → Segurança
├── data/
│   └── fazendas.json  → Banco de dados das fazendas
└── uploads/           → Fotos e vídeos (criada automaticamente)
```

## Como fazer upload no servidor

Suba a pasta `empreendimentos/` completa para dentro de `/public_html/` no HostGator.

Resultado:
- Site público: https://fontecinfo.com/empreendimentos/
- Painel admin: https://fontecinfo.com/empreendimentos/admin.php

## Senha do painel admin

Padrão: `Fontec@2026`

Para alterar, edite a linha no arquivo `admin.php`:
```php
define('ADMIN_SENHA', 'SuaNovaSenha');
```

## Permissões necessárias no cPanel

A pasta `uploads/` e `data/` precisam de permissão 755.
O arquivo `data/fazendas.json` precisa de permissão 644.

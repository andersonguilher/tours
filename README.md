# ✈️ Tours & Events Management System for Virtual Airlines

Plataforma **avançada e extensível** para gerenciamento completo de **Tours, Eventos e Progressão de Pilotos** em **Companhias Aéreas Virtuais (Virtual Airlines – VA)**.  
O sistema foi projetado para **automatizar processos operacionais**, aumentar o **engajamento dos pilotos** e fornecer ao staff **controle total** sobre eventos, validações e recompensas.

> 🚀 Ideal para VAs que desejam profissionalizar seus tours, reduzir validações manuais e oferecer uma experiência moderna aos pilotos.

---

## 📖 Índice

- [Visão Geral](#-visão-geral)
- [Principais Diferenciais](#-principais-diferenciais)
- [Funcionalidades](#-funcionalidades)
  - [Pilotos](#para-pilotos)
  - [Administração](#painel-administrativo)
- [Tecnologias Utilizadas](#-tecnologias-utilizadas)
- [Requisitos](#-requisitos-do-sistema)
- [Instalação e Configuração](#-instalação-e-configuração)
- [SimBrief API](#-integração-com-simbrief)
- [Automação e Validação](#-automação-e-validação-de-voos)
- [Estrutura do Projeto](#-estrutura-do-projeto)
- [Boas Práticas de Segurança](#-boas-práticas-de-segurança)
- [Contribuição](#-contribuição)
- [Licença](#-licença)

---

## 📌 Visão Geral

O **Tours & Events Management System** é um sistema modular desenvolvido em **PHP**, focado em VAs que realizam **tours estruturados**, **eventos especiais** e **campanhas de engajamento**.

Ele centraliza:
- Validação automática de voos
- Progressão de carreira dos pilotos
- Emissão de certificados e badges
- Integração com SimBrief
- Rankings e estatísticas

Tudo isso reduzindo a necessidade de validações manuais e aumentando a confiabilidade dos dados.

---

## 🌟 Principais Diferenciais

- ✅ **Validação automática de voos baseada em dados reais**
- 🔄 **Integração nativa com SimBrief**
- 🏅 **Sistema completo de ranks, badges e progressão**
- 📘 **Passaporte digital visual**
- 📄 **Certificados em PDF gerados automaticamente**
- 📊 **Rankings e estatísticas em tempo real**
- 🔐 **Configuração segura fora do repositório**
- ⚙️ **Estrutura modular e extensível**

---

## 🚀 Funcionalidades

### 👨‍✈️ Para Pilotos

- **Tours Estruturados**
  - Visualização de tours ativos, encerrados e futuros
  - Detalhes completos de cada perna (leg)

- **Planejamento com SimBrief**
  - Integração direta via API v1
  - Validação baseada no OFP real do piloto

- **Live Board**
  - Acompanhamento de voos em tempo real
  - Status e progresso do piloto

- **Passaporte Digital**
  - Histórico visual de tours concluídos
  - Selos e conquistas exibidos graficamente  
  Arquivo: `passport_book.php`

- **Sistema de Ranks**
  - Progressão automática baseada em critérios configuráveis
  - Acúmulo de pontos e experiência

- **Certificados Automáticos**
  - Geração de certificados personalizados em PDF
  - Utiliza biblioteca `FPDF`
  - Emitidos automaticamente ao concluir um tour

- **Rankings**
  - Classificação geral e por tour
  - Incentivo à competitividade saudável

---

### 🛠️ Painel Administrativo

- **Gerenciamento de Tours**
  - Criar, editar, publicar e finalizar tours
  - Definir regras, datas e critérios

- **Gerenciamento de Legs**
  - Configuração detalhada de rotas, aeronaves e requisitos
  - Associação direta com SimBrief

- **Ranks e Badges**
  - Criação de níveis personalizados
  - Definição de badges e critérios de obtenção

- **Validação Automática de Voos**
  - Script dedicado:
    ```
    scripts/validate_flights.php
    ```
  - Pode ser executado manualmente ou via **cron**

- **Gestão de Frota SimBrief**
  - Cache inteligente de aeronaves
  - Atualização via AJAX  
    (`ajax_simbrief_aircraft.php`)

---

## 🧰 Tecnologias Utilizadas

- **Backend:** PHP 7.4+
- **Banco de Dados:** MySQL / MariaDB
- **Integrações:** SimBrief API
- **PDF:** FPDF
- **Frontend:** HTML, CSS, JavaScript
- **Arquitetura:** Modular e orientada a serviços

---

## 🛠️ Requisitos do Sistema

- PHP 7.4 ou superior
- MySQL ou MariaDB
- Servidor Web: Apache ou Nginx
- Extensões PHP:
  - pdo
  - pdo_mysql
  - json
  - gd
  - mbstring

---

## 📦 Instalação e Configuração

### 1️⃣ Banco de Dados

Importe o schema inicial:

```sql
source tours.sql;
```

---

### 2️⃣ Arquivo de Configuração (Segurança)

O sistema **não armazena credenciais no repositório**.

📍 Caminho padrão:
```
/var/www/kafly_user/data/www/config_db.php
```

```php
<?php
define('DB_SERVERNAME', 'localhost');
define('DB_VOOS_USER', 'usuario_db');
define('DB_VOOS_PASS', 'senha_db');
define('DB_VOOS_NAME', 'kafly_tracker');

define('SIMBRIEF_API_KEY', 'SUA_API_KEY');
?>
```

---

### 3️⃣ Permissões

```bash
chmod -R 775 assets/banners cache
```

---

## 🔧 Integração com SimBrief

- Necessária para:
  - Planejamento de voo
  - Validação automática
- API Key deve ser privada
- Cache evita excesso de requisições

---

## ⏱️ Automação e Validação de Voos

Recomendado executar via **cron**:

```bash
*/5 * * * * /usr/bin/php /caminho/scripts/validate_flights.php
```

---

## 📂 Estrutura do Projeto

```
admin/      → Painel administrativo
pilots/     → Área dos pilotos
includes/   → Bibliotecas e helpers
config/     → Configurações locais
scripts/    → Validações e automações
assets/     → Banners e imagens
cache/      → Cache SimBrief
```

---

## 🔐 Boas Práticas de Segurança

- Credenciais fora do repositório
- API Keys não versionadas
- Permissões restritas de escrita
- Scripts críticos isolados

---

## 🤝 Contribuição

1. Fork do projeto
2. Crie sua branch:
   ```bash
   git checkout -b feature/NovaFeature
   ```
3. Commit:
   ```bash
   git commit -m "Adiciona NovaFeature"
   ```
4. Push:
   ```bash
   git push origin feature/NovaFeature
   ```
5. Pull Request

---

## 📄 Licença

Consulte o arquivo de licença no repositório.

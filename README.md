# ✈️ Kafly Tours System

Sistema completo de gestão de **Tours e Eventos** para **Companhias Aéreas Virtuais (VA)**, com validação automática de voos (**IVAO/VATSIM**), gamificação e emissão de certificados.

![Status](https://img.shields.io/badge/Status-Active-success)
![Version](https://img.shields.io/badge/Version-1.2.0-blue)
![Tech](https://img.shields.io/badge/PHP-7.4%2B-purple)

---

## 📋 Funcionalidades

### 👨‍✈️ Para Pilotos
- **Dashboard Interativo:** Visualização de Tours ativos, datas de vigência e progresso.
- **Mapas em Tempo Real:** Visualização da rota com **LeafletJS**, mostrando pernas voadas, ativas e pendentes.
- **Flight Tools:** Integração direta com **SimBrief** (geração de OFP) e METAR em tempo real.
- **Gamificação (Passaporte):** Perfil de conquistas com medalhas (*Badges*) e estatísticas de voo.
- **Certificados Automáticos:** Geração de certificados em PDF com assinaturas digitais e hash de validação ao completar um Tour.

### 👮‍♂️ Para Staff (Admin)
- **Gestão de Tours:** Criação e edição de eventos, definição de datas, banners e regras (Aeronaves, Velocidade, Rede).
- **Gestão de Rotas:** Interface para adicionar/remover pernas (*Legs*) com sugestão inteligente de ICAO.
- **Central de Medalhas:** Upload e gestão de *Badges* para o passaporte.
- **Segurança:** Painel protegido com verificação de permissões do WordPress (`current_user_can`).

### 🤖 Automação (Backend)
- **Tracker Automático:** Script via **Cron Job** que monitoriza a rede (*Whazzup JSON*) a cada 2 minutos.
- **Validação Rigorosa:** Verificação de Callsign, Aeronave, Rota e Status (*Landed / On Blocks*).
- **Landing Rate:** Registo da suavidade do toque (*fpm*) no histórico.
- **Discord Webhooks:** Notificações automáticas no Discord ao completar uma perna ou finalizar um Tour.

---

## 🚀 Instalação

### 1. Requisitos
- PHP **7.4 ou superior** (com **cURL** e **PDO** ativados).
- MySQL / MariaDB.
- WordPress (para autenticação de utilizadores).
- Acesso ao **Crontab** (para o tracker).

### 2. Estrutura de Pastas
Certifique-se de que as seguintes pastas existem e possuem permissão de escrita (`chmod 755` ou `777`):

```text
/dash/tours/
├── admin/          # Painel Administrativo
├── pilots/         # Área do Piloto (Frontend)
├── scripts/        # Scripts de Automação (Cron)
├── config/         # Conexão com Banco de Dados
└── assets/
    ├── banners/    # Imagens dos Tours
    ├── badges/     # Imagens das Medalhas
    └── signatures/ # Assinaturas para o Certificado
```

### 3. Banco de Dados
Importe o esquema SQL contendo as seguintes tabelas:

- `tours`
- `tour_legs`
- `pilot_tour_progress`
- `pilot_leg_history`
- `badges`
- `pilot_badges`

### 4. Configuração
Edite o arquivo:

```php
config/db.php
```

Configure:
- Banco de dados do sistema de Tours.
- Conexão com o banco do **WordPress / Pilotos**.

---

## ⚙️ Configuração do Tracker

Configure uma **Cron Job** para rodar a cada **2 ou 5 minutos**:

```bash
*/2 * * * * /usr/bin/php /caminho/completo/para/dash/tours/scripts/validate_flights.php
```

> **Nota:**  
> Edite o ficheiro `scripts/validate_flights.php` e adicione a sua **Webhook URL do Discord** na função `sendDiscordWebhook()`.

---

## 📜 Certificados PDF (FPDF)

O sistema utiliza a biblioteca **FPDF** para geração de certificados.

1. Baixe a biblioteca em https://www.fpdf.org ou GitHub  
2. Coloque `fpdf.php` e a pasta `font/` dentro de `/pilots/`
3. Adicione as assinaturas (PNG transparente) em `/assets/signatures/`

Arquivos esperados:
```
rubrica_diretor.png
rubrica_eventos.png
```

---

## 🛠️ Tecnologias Utilizadas

- **Backend:** PHP (Native), MySQL
- **Frontend:** HTML5, Tailwind CSS, JavaScript
- **Mapas:** LeafletJS + CartoDB Dark Matter
- **PDF:** FPDF Library
- **Integrações:** Discord API, IVAO Whazzup API, SimBrief Dispatch

---

## 📝 Licença

Este projeto foi desenvolvido para **uso exclusivo da Kafly Virtual Airline**.

**Desenvolvido por:** Anderson Guilherme

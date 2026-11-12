# S1QLBR

Gerador gratuito de queries para threat hunting, compatível com o Event Search do SentinelOne.
Você escreve o que quer caçar em português, inglês, espanhol ou chinês e copia a query pronta.

Site: https://s1qlbr.com

## O que tem no site

| Página | Endereço | Situação |
|---|---|---|
| Gerador de queries | `/` | no ar |
| Análise de resultados | `/analise/` | em breve |
| Apoie o projeto | `/donate/donate.html` | em breve |
| Fale conosco | `/contato/contato.php` | no ar |

## Estrutura

```
public_html/              → vai para a pasta pública da hospedagem
├── index.html            gerador (regras locais + motores de IA)
├── api.php               intermediário da IA grátis (Groq → Gemini)
├── menu.js               menu do topo, usado por todas as páginas
├── s1.css                visual das páginas internas
├── contato/contato.php   formulário que envia e-mail
├── analise/index.html    análise de resultados (em breve)
└── donate/donate.html    doação (em breve)
s1qlbr-config.modelo.php  modelo da configuração (sem chaves)
LEIA-ME-hostgator.txt     passo a passo de instalação
identidade.md             logo, cores e fontes
```

## Configuração e segredos

As chaves de API e o e-mail de contato ficam em `s1qlbr-config.php`, **fora** da pasta pública.
Esse arquivo **não vai para o repositório** (está no `.gitignore`).
Copie `s1qlbr-config.modelo.php`, renomeie para `s1qlbr-config.php` e preencha no servidor.

## Privacidade

- Sem IA, nada do que é digitado sai do navegador.
- Com a IA grátis, o pedido passa pelo `api.php` só para chegar ao provedor e não é gravado.
- O Fale conosco manda a mensagem direto por e-mail e não guarda cópia.
- Os limites de uso guardam apenas um hash do IP.

## Aviso

Projeto independente. Compatível com SentinelOne, sem vínculo com a empresa.
SentinelOne e S1QL são marcas dos respectivos donos.

<?php
/**
 * S1QLBR — configuração do intermediário de IA e do Fale conosco.
 * Este arquivo fica FORA da public_html (ex.: /home/SEU_USUARIO/s1qlbr-config.php).
 * Nunca envie este arquivo para o GitHub nem para ninguém.
 */
return [
    // true = mostra na página o motivo técnico dos erros (sem expor a chave). Volte para false quando estiver funcionando.
    'debug' => false,

    // Sites que podem usar o intermediário e o formulário de contato
    'allowed_origins' => ['https://s1qlbr.com', 'https://www.s1qlbr.com', 'https://s1qlbr.com.br', 'https://www.s1qlbr.com.br'],

    // Troque por uma frase longa e aleatória (serve para anonimizar o IP nos limites)
    'salt' => 'TROQUE-POR-UMA-FRASE-LONGA-E-ALEATORIA',

    // Pasta onde ficam só os contadores de uso (fora da public_html)
    'data_dir' => __DIR__ . '/s1qlbr-data',

    // Limites de uso
    'max_request_chars' => 300,   // tamanho máximo do pedido
    'max_output_tokens' => 600,   // tamanho máximo da resposta da IA
    'per_ip_hour'       => 20,    // consultas por pessoa por hora
    'per_ip_day'        => 60,    // consultas por pessoa por dia

    // Fale conosco (contato/contato.php)
    'contact' => [
        'to'          => 'SEU_EMAIL@exemplo.com',   // para onde vão as mensagens (pode ser seu Gmail)
        'from'        => 'contato@s1qlbr.com',      // remetente: crie esta conta em cPanel → Contas de e-mail
        'per_ip_hour' => 5,                         // mensagens por pessoa por hora
        'per_ip_day'  => 15,                        // mensagens por pessoa por dia
    ],

    // Modo automático: tenta nesta ordem; se o primeiro falhar ou ficar sem cota, usa o próximo
    'auto_order' => ['groq', 'gemini'],

    'providers' => [
        'gemini' => [
            'key'       => 'COLE_A_CHAVE_DO_GEMINI',        // https://aistudio.google.com/apikey
            // lista de modelos: se um estiver lotado, sem cota ou fora do ar, tenta o próximo
            'model'     => ['gemini-3.7-flash', 'gemini-3.5-flash', 'gemini-3.8-flash', 'gemini-flash-latest'],
            'daily_cap' => 1000,                            // teto diário de consultas neste motor
            'max_output_tokens' => 2048,                    // o modelo "pensa" antes de responder; precisa de folga
            'generation' => ['thinkingConfig' => ['thinkingLevel' => 'low']],
            'timeout'   => 25,                              // segundos esperando cada modelo
        ],
        'groq' => [
            'key'       => 'COLE_A_CHAVE_DO_GROQ',          // https://console.groq.com/keys
            'model'     => ['openai/gpt-oss-120b', 'openai/gpt-oss-20b'],
            'daily_cap' => 1000,
            'max_output_tokens' => 2048,
            'body'      => ['reasoning_effort' => 'low', 'include_reasoning' => false],
            'timeout'   => 40,
        ],
    ],
];

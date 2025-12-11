<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agostini ERP - Offline</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
            padding: 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 350px;
        }

        img {
            width: 90px;
            opacity: 0.9;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        p {
            margin-top: 0;
            font-size: 15px;
            color: #555;
        }

        .retry {
            margin-top: 20px;
            padding: 12px 20px;
            font-size: 16px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .retry:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>

    <div class="card">
        <img src="/images/icons/icon-192x192.png" alt="Agostini Logo">
        <h1>Sem Conexão</h1>
        <p>Você está offline. Algumas funcionalidades podem não estar disponÃ­veis.</p>

        <button class="retry" onclick="location.reload()">Tentar Novamente</button>
    </div>

</body>
</html>
<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class UserFacingError
{
    /**
     * Convert an exception into information that is safe to show in production.
     *
     * @return array{title: string, message: string, cause: string, category: string, guidance: string, status: int, occurred_at: string, path: string}
     */
    public function summarize(Throwable $exception, ?Request $request = null): array
    {
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        $exception = $this->originalException($exception);

        [$title, $message, $cause, $category, $guidance] = match (true) {
            $exception instanceof DecryptException => ['QR Code não reconhecido', 'O código informado não pôde ser validado pelo sistema.', 'O conteúdo está incompleto, foi alterado ou não pertence a esta aplicação.', 'Código inválido ou incompleto', 'Copie novamente todo o conteúdo do QR Code. Se continuar, gere um QR Code atualizado da ordem de produção.'],
            $exception instanceof ValidationException => ['Dados precisam ser corrigidos', 'A operação foi interrompida porque uma informação não passou pela validação.', $this->firstValidationMessage($exception), 'Dados que precisam de correção', 'Corrija a informação indicada na causa e envie o formulário novamente.'],
            $exception instanceof AuthenticationException || $status === 401 => ['Sua sessão terminou', 'O servidor não reconheceu mais a autenticação deste acesso.', 'A sessão expirou ou o usuário foi desconectado.', 'Sessão encerrada', 'Atualize a página, entre novamente e repita a operação. Os dados não foram confirmados.'],
            $exception instanceof AuthorizationException || $status === 403 => ['Operação não permitida', 'O servidor recebeu a solicitação, mas bloqueou sua execução.', 'Seu usuário não possui a permissão exigida para esta operação.', 'Permissão insuficiente', 'Solicite a permissão ao responsável pelo sistema ou utilize um usuário autorizado.'],
            $exception instanceof ModelNotFoundException || $status === 404 => ['Registro não encontrado', 'A informação solicitada não está mais disponível no servidor.', 'O registro pode ter sido removido, alterado ou o endereço está incorreto.', 'Registro não localizado', 'Atualize a página, localize o registro novamente e repita a operação.'],
            $exception instanceof QueryException => ['Não foi possível salvar ou consultar os dados', 'O banco de dados recusou ou não conseguiu executar a operação.', $this->databaseCause($exception), 'Falha no banco de dados', $this->databaseGuidance($exception)],
            $status === 419 => ['A página expirou', 'O código de segurança desta página perdeu a validade.', 'A página ficou aberta por muito tempo ou a sessão foi renovada em outra aba.', 'Página expirada', 'Atualize a página e repita a operação. Se havia um formulário, confira os dados antes de enviar novamente.'],
            $status === 429 => ['Aguarde antes de tentar novamente', 'O servidor bloqueou temporariamente novas solicitações.', 'Foram realizadas muitas tentativas em um intervalo curto.', 'Limite temporário de solicitações', 'Aguarde pelo menos um minuto e tente novamente apenas uma vez.'],
            $status === 503 => ['Sistema temporariamente indisponível', 'O servidor não está pronto para concluir esta operação.', 'O serviço está em manutenção, reiniciando ou temporariamente sobrecarregado.', 'Serviço indisponível', 'Aguarde alguns minutos e tente novamente. Se persistir, informe o horário ao responsável.'],
            default => ['Não foi possível concluir a operação', 'A operação foi interrompida por um erro identificado no servidor.', $this->safeTechnicalCause($exception), 'Erro interno do sistema', 'Confira a causa abaixo, corrija os dados se isso for possível e tente novamente. Se persistir, envie a causa, o horário e a página ao responsável.'],
        };

        return [
            'title' => $title,
            'message' => $message,
            'cause' => $cause,
            'category' => $category,
            'guidance' => $guidance,
            'status' => $status,
            'occurred_at' => now()->format('d/m/Y H:i:s'),
            'path' => '/'.ltrim($request?->path() ?? request()->path(), '/'),
        ];
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) && $message !== ''
            ? $message
            : 'Algumas informações precisam ser corrigidas antes de continuar.';
    }

    private function databaseCause(QueryException $exception): string
    {
        $message = $exception->getMessage();

        if ($uniqueField = $this->uniqueConstraintField($message)) {
            if ($uniqueField['table'] === 'products' && $uniqueField['column'] === 'sku') {
                return 'Não foi possível cadastrar este produto porque o SKU informado já pertence a outro produto.';
            }

            return "Já existe {$uniqueField['record']} com este {$uniqueField['field']}. Esse valor precisa ser diferente em cada cadastro.";
        }

        return match (true) {
            preg_match('/SQLSTATE\[(23000|23505)\]/', $message) === 1 => 'Já existe outro cadastro utilizando uma informação que precisa ser exclusiva.',
            preg_match('/SQLSTATE\[(23503)\]|(?:1451|1452)/', $message) === 1 => 'Um registro relacionado não existe ou ainda está sendo utilizado por outro cadastro.',
            preg_match('/SQLSTATE\[(23502)\]|Column .* cannot be null|(?:1048)/i', $message) === 1 => 'Um campo obrigatório chegou vazio ao banco de dados.',
            preg_match('/SQLSTATE\[(08\w+)\]|Connection refused|server has gone away/i', $message) === 1 => 'A conexão com o banco de dados foi interrompida ou está indisponível.',
            default => 'O banco de dados devolveu o código '.($exception->errorInfo[0] ?? 'não informado').' e não concluiu a solicitação.',
        };
    }

    private function databaseGuidance(QueryException $exception): string
    {
        if ($uniqueField = $this->uniqueConstraintField($exception->getMessage())) {
            if ($uniqueField['table'] === 'products' && $uniqueField['column'] === 'sku') {
                return 'Feche esta mensagem e pesquise o SKU informado na lista de produtos. Se o produto já existir, abra e edite esse cadastro. Se for realmente um produto novo, informe um SKU diferente e tente salvar novamente.';
            }

            return "Pesquise se {$uniqueField['record']} já está cadastrado. Se estiver, edite o cadastro existente; caso contrário, informe outro valor para {$uniqueField['article']} {$uniqueField['field']} e salve novamente.";
        }

        $cause = $this->databaseCause($exception);

        return match (true) {
            str_contains($cause, 'Já existe') => 'Revise campos como código, nome ou documento e remova valores duplicados antes de salvar novamente.',
            str_contains($cause, 'relacionado') => 'Atualize a página e selecione novamente os registros relacionados antes de repetir a operação.',
            str_contains($cause, 'obrigatório') => 'Revise os campos obrigatórios do formulário e preencha o que estiver faltando.',
            str_contains($cause, 'conexão') => 'Aguarde alguns instantes e tente novamente. Se persistir, verifique o servidor do banco de dados.',
            default => 'Não repita várias vezes. Informe o código, o horário e a página ao responsável pelo sistema.',
        };
    }

    /**
     * Translate database constraint identifiers without showing table/column jargon.
     *
     * @return array{record: string, field: string, article: string, table: string|null, column: string}|null
     */
    private function uniqueConstraintField(string $message): ?array
    {
        $table = null;
        $column = null;

        if (preg_match('/UNIQUE constraint failed:\s*([\w]+)\.([\w]+)/i', $message, $matches)) {
            [, $table, $column] = $matches;
        } elseif (preg_match('/Key \(([^)]+)\)=\([^)]+\) already exists/i', $message, $matches)) {
            $column = $matches[1];
        } elseif (preg_match('/Duplicate entry .* for key [\'"`](?:[\w]+\.)?([\w]+)[\'"`]/i', $message, $matches)) {
            $column = preg_replace('/_(?:unique|uniq)$/i', '', $matches[1]);
        }

        if (! $column) {
            return null;
        }

        $records = [
            'products' => 'um produto cadastrado',
            'clients' => 'um cliente cadastrado',
            'users' => 'um usuário cadastrado',
            'companies' => 'uma empresa cadastrada',
            'production_orders' => 'uma ordem de produção cadastrada',
        ];
        $fields = [
            'sku' => ['SKU', 'o'],
            'barcode' => ['código de barras', 'o'],
            'taxNumber' => ['CNPJ ou CPF', 'o'],
            'tax_number' => ['CNPJ ou CPF', 'o'],
            'email' => ['e-mail', 'o'],
            'username' => ['nome de usuário', 'o'],
            'name' => ['nome', 'o'],
            'order_number' => ['número da ordem', 'o'],
        ];
        [$field, $article] = $fields[$column] ?? [str_replace('_', ' ', $column), 'o'];

        return [
            'record' => $records[$table] ?? 'outro registro cadastrado',
            'field' => $field,
            'article' => $article,
            'table' => $table,
            'column' => $column,
        ];
    }

    private function originalException(Throwable $exception): Throwable
    {
        // Em produção, o Laravel transforma erros comuns em HttpException 500
        // antes de renderizar a página. A causa imediatamente anterior contém
        // o erro que realmente explica o problema ao usuário.
        if ($exception instanceof HttpExceptionInterface && $exception->getPrevious()) {
            return $exception->getPrevious();
        }

        return $exception;
    }

    private function safeTechnicalCause(Throwable $exception): string
    {
        while ($exception->getPrevious()) {
            $exception = $exception->getPrevious();
        }

        $message = trim($exception->getMessage());

        if ($message === '') {
            return 'O servidor não forneceu uma descrição adicional para este erro.';
        }

        // Remover detalhes que ajudam o suporte no log, mas não devem chegar ao navegador.
        $message = preg_replace('/\s*\(Connection:.*$/is', '', $message) ?? $message;
        $message = preg_replace('/\b(password|passwd|secret|token|authorization)\s*[=:]\s*[^\s,;]+/i', '$1=[protegido]', $message) ?? $message;
        $message = preg_replace('#(?:[A-Za-z]:\\\\|/)[^\s:]+(?:\.php)?(?::\d+)?#', '[caminho interno]', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;

        return mb_strimwidth($message, 0, 500, '…');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->uuid('uuid')->primary(); // Chave primária UUID

            // Chave estrangeira para a empresa
            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', column: 'uuid') // Vincula à tabela 'companies'
                ->cascadeOnDelete(); // Exclui matérias-primas se a empresa for excluída

            $table->string('name'); // Nome da matéria-prima
            $table->string('sku')->unique()->nullable(); // Código único (opcional)
            $table->text('description')->nullable(); // Descrição (opcional)
            $table->string('unit_of_measure')->default('unidade'); // Unidade de medida (ex: peça, kg, litro)
            $table->decimal('standard_cost', 8, 2)->nullable(); // Custo padrão (opcional)
            $table -> integer('stock') -> default (0);
            $table->timestamps(); // created_at e updated_at
            $table->softDeletes(); // deleted_at (exclusão lógica)

            // Índices para otimizar buscas por empresa e SKU/nome
            $table->index(['company_id', 'sku']);
            $table->index(['company_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_materials');
    }
};

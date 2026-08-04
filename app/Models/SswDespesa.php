<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SswDespesa extends Model
{
    use HasFactory;

    protected $table = 'ssw_despesas';

    // Os campos que o nosso robô terá permissão para preencher e atualizar
    protected $fillable = [
        'lancamento',
        'inclusao',
        'filial',
        'evento',
        'historico',
        'fornecedor',
        'nota_fiscal',
        'valor',
        'vencimento',
        'pagamento',
        'competencia',
        'situacao',
    ];

    // Avisando o Laravel que esses campos são datas reais para facilitar os cálculos
    protected $casts = [
        'inclusao' => 'date',
        'vencimento' => 'date',
        'pagamento' => 'date',
        'valor' => 'decimal:2',
    ];
}
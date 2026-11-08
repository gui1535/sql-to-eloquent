<?php

namespace SqlToEloquent\Tests\Geracao;

use PHPUnit\Framework\TestCase;
use SqlToEloquent\Analise\AnalisadorMysql;
use SqlToEloquent\Dominio\TipoArtefato;
use SqlToEloquent\Geracao\Geradores\GeradorModel;
use SqlToEloquent\Planejamento\OpcoesGeracao;

class GeradorModelTest extends TestCase
{
    public function test_gera_models_com_relacionamentos(): void
    {
        $schema = (new AnalisadorMysql)->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $arquivos = (new GeradorModel)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Model],
                caminhos: ['models' => '/tmp/Models'],
                namespaceModels: 'App\\Models',
                tabelaUsers: 'usuarios',
            ),
        );

        $porTabela = [];
        foreach ($arquivos as $arquivo) {
            $porTabela[$arquivo->tabela] = $arquivo->conteudo;
            $this->assertTrue($this->sintaxeValida($arquivo->conteudo), $arquivo->caminho);
        }

        $this->assertArrayHasKey('usuarios', $porTabela);
        $this->assertArrayHasKey('pedidos', $porTabela);
        $this->assertArrayHasKey('itens_pedido', $porTabela);

        $usuario = $porTabela['usuarios'];
        $this->assertStringContainsString('namespace App\\Models;', $usuario);
        $this->assertStringContainsString('class User extends Authenticatable', $usuario);
        $this->assertStringContainsString('use Notifiable;', $usuario);
        $this->assertStringContainsString("protected \$table = 'usuarios';", $usuario);
        $this->assertStringContainsString("public const CREATED_AT = 'criado_em';", $usuario);
        $this->assertStringContainsString("public const UPDATED_AT = 'atualizado_em';", $usuario);
        $this->assertStringContainsString("'nome',", $usuario);
        $this->assertStringContainsString("'senha',", $usuario);
        $this->assertStringContainsString("protected \$hidden = [", $usuario);
        $this->assertStringContainsString("'ativo' => 'boolean'", $usuario);
        $this->assertStringContainsString("'senha' => 'hashed'", $usuario);
        $this->assertStringContainsString('function pedidos(): HasMany', $usuario);
        $this->assertStringContainsString("return \$this->hasMany(Pedido::class, 'usuario_id', 'id');", $usuario);

        $pedido = $porTabela['pedidos'];
        $this->assertStringContainsString('class Pedido extends Model', $pedido);
        $this->assertStringContainsString('public $timestamps = false;', $pedido);
        $this->assertStringContainsString('function usuario(): BelongsTo', $pedido);
        $this->assertStringContainsString("return \$this->belongsTo(User::class, 'usuario_id', 'id');", $pedido);
        $this->assertStringContainsString('function itensPedido(): HasMany', $pedido);

        $item = $porTabela['itens_pedido'];
        $this->assertStringContainsString('class ItemPedido extends Model', $item);
        $this->assertStringContainsString('function pedido(): BelongsTo', $item);
        $this->assertStringContainsString('function produto(): BelongsTo', $item);
        $this->assertStringContainsString("'quantidade',", $item);
    }

    public function test_aplica_soft_deletes_quando_solicitado(): void
    {
        $schema = (new AnalisadorMysql)->analisar(<<<'SQL'
            CREATE TABLE posts (
              id bigint unsigned NOT NULL AUTO_INCREMENT,
              titulo varchar(255) NOT NULL,
              PRIMARY KEY (id)
            );
        SQL);

        $arquivo = (new GeradorModel)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Model],
                caminhos: ['models' => 'app/Models'],
                tabelasSoftDelete: ['posts'],
            ),
        )[0];

        $this->assertStringContainsString('use Illuminate\\Database\\Eloquent\\SoftDeletes;', $arquivo->conteudo);
        $this->assertStringContainsString('use SoftDeletes;', $arquivo->conteudo);
    }

    public function test_detecta_has_one_quando_fk_unica(): void
    {
        $schema = (new AnalisadorMysql)->analisar(<<<'SQL'
            CREATE TABLE usuarios (
              id bigint unsigned NOT NULL AUTO_INCREMENT,
              nome varchar(255) NOT NULL,
              PRIMARY KEY (id)
            );

            CREATE TABLE perfis (
              id bigint unsigned NOT NULL AUTO_INCREMENT,
              usuario_id bigint unsigned NOT NULL,
              bio text NULL,
              PRIMARY KEY (id),
              UNIQUE KEY perfis_usuario_id_unique (usuario_id),
              CONSTRAINT perfis_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
            );
        SQL);

        $arquivos = (new GeradorModel)->gerar(
            $schema,
            new OpcoesGeracao(
                artefatos: [TipoArtefato::Model],
                caminhos: ['models' => 'app/Models'],
            ),
        );

        $usuario = $arquivos[0]->tabela === 'usuarios' ? $arquivos[0]->conteudo : $arquivos[1]->conteudo;

        $this->assertStringContainsString('function perfil(): HasOne', $usuario);
        $this->assertStringContainsString("return \$this->hasOne(Perfil::class", $usuario);
    }

    private function sintaxeValida(string $conteudo): bool
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'model_').'.php';
        file_put_contents($arquivo, $conteudo);
        exec('php -l '.escapeshellarg($arquivo).' 2>&1', $saida, $codigo);
        unlink($arquivo);

        return $codigo === 0;
    }
}

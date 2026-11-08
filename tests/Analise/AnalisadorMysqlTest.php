<?php

namespace SqlToEloquent\Tests\Analise;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlToEloquent\Analise\AnalisadorMysql;

class AnalisadorMysqlTest extends TestCase
{
    private AnalisadorMysql $analisador;

    protected function setUp(): void
    {
        $this->analisador = new AnalisadorMysql;
    }

    public function test_falha_quando_arquivo_nao_existe(): void
    {
        $this->expectException(RuntimeException::class);

        $this->analisador->analisarArquivo('/caminho/inexistente.sql');
    }

    public function test_analisa_schema_basico_do_fixture(): void
    {
        $schema = $this->analisador->analisarArquivo(
            __DIR__.'/../fixtures/schema_basico.sql'
        );

        $this->assertSame(
            ['usuarios', 'produtos', 'pedidos', 'itens_pedido'],
            $schema->nomesTabelas()
        );

        $usuarios = $schema->tabela('usuarios');
        $this->assertNotNull($usuarios);
        $this->assertSame(['id'], $usuarios->chavePrimaria);
        $this->assertSame('InnoDB', $usuarios->mecanismo);
        $this->assertSame('utf8mb4', $usuarios->charset);
        $this->assertCount(8, $usuarios->colunas);
        $this->assertCount(2, $usuarios->indices);

        $id = $usuarios->coluna('id');
        $this->assertNotNull($id);
        $this->assertSame('bigint', $id->tipo);
        $this->assertTrue($id->unsigned);
        $this->assertTrue($id->autoIncremento);
        $this->assertFalse($id->nulo);

        $email = $usuarios->coluna('email');
        $this->assertNotNull($email);
        $this->assertSame(255, $email->tamanho);

        $perfil = $usuarios->coluna('perfil');
        $this->assertNotNull($perfil);
        $this->assertSame(['admin', 'cliente'], $perfil->valoresEnum);
        $this->assertSame('cliente', $perfil->padrao);

        $produtos = $schema->tabela('produtos');
        $this->assertNotNull($produtos);
        $this->assertSame(['id'], $produtos->chavePrimaria);

        $preco = $produtos->coluna('preco');
        $this->assertNotNull($preco);
        $this->assertSame(10, $preco->precisao);
        $this->assertSame(2, $preco->escala);

        $pedidos = $schema->tabela('pedidos');
        $this->assertNotNull($pedidos);
        $this->assertCount(1, $pedidos->chavesEstrangeiras);
        $this->assertSame('usuarios', $pedidos->chavesEstrangeiras[0]->tabelaReferenciada);
        $this->assertSame('cascade', $pedidos->chavesEstrangeiras[0]->aoDeletar);
        $this->assertSame('restrict', $pedidos->chavesEstrangeiras[0]->aoAtualizar);

        $itens = $schema->tabela('itens_pedido');
        $this->assertNotNull($itens);
        $this->assertCount(2, $itens->chavesEstrangeiras);

        $produtos = $schema->tabela('produtos');
        $this->assertNotNull($produtos->coluna('sku'));
        $this->assertSame('varchar', $produtos->coluna('sku')?->tipo);
        $nomesIndices = array_map(fn ($indice) => $indice->nome, $produtos->indices);
        $this->assertContains('produtos_estoque_index', $nomesIndices);

        $this->assertNotEmpty($schema->avisos);
        $this->assertTrue($this->avisoContem($schema->avisos, 'CREATE VIEW'));
    }

    public function test_aplica_alter_table_e_create_index(): void
    {
        $schema = $this->analisador->analisar(<<<'SQL'
            CREATE TABLE produtos (
              id bigint unsigned NOT NULL AUTO_INCREMENT,
              nome varchar(120) NOT NULL,
              PRIMARY KEY (id)
            );

            ALTER TABLE produtos
              ADD COLUMN preco decimal(10,2) NOT NULL DEFAULT 0.00,
              ADD INDEX produtos_nome_index (nome);

            CREATE UNIQUE INDEX produtos_nome_unique ON produtos (nome);
        SQL);

        $produtos = $schema->tabela('produtos');
        $this->assertNotNull($produtos);
        $this->assertNotNull($produtos->coluna('preco'));
        $this->assertSame(10, $produtos->coluna('preco')?->precisao);
        $this->assertCount(2, $produtos->indices);
    }

    public function test_captura_insert_e_ignora_set_drop_sem_aviso(): void
    {
        $schema = $this->analisador->analisar(<<<'SQL'
            SET NAMES utf8mb4;
            DROP TABLE IF EXISTS usuarios;
            INSERT INTO usuarios (nome) VALUES ('Ana');
            CREATE TABLE usuarios (
              id int NOT NULL AUTO_INCREMENT,
              nome varchar(100) NOT NULL,
              PRIMARY KEY (id)
            );
        SQL);

        $this->assertSame(['usuarios'], $schema->nomesTabelas());
        $this->assertTrue($schema->temDados());
        $this->assertSame('usuarios', $schema->insercoes[0]->tabela);
        $this->assertSame(['nome'], $schema->insercoes[0]->colunas);
        $this->assertSame([['Ana']], $schema->insercoes[0]->linhas);
        $this->assertSame([], $schema->avisos);
    }

    public function test_suporta_comentario_e_aspas_no_sql(): void
    {
        $schema = $this->analisador->analisar(<<<'SQL'
            /* bloco */
            -- linha
            CREATE TABLE `pedidos` (
              `id` int NOT NULL COMMENT 'chave',
              `obs` varchar(50) DEFAULT 'a;b'
            );
        SQL);

        $tabela = $schema->tabela('pedidos');
        $this->assertNotNull($tabela);
        $this->assertSame('chave', $tabela->coluna('id')?->comentario);
        $this->assertSame('a;b', $tabela->coluna('obs')?->padrao);
    }

    /** @param list<string> $avisos */
    private function avisoContem(array $avisos, string $trecho): bool
    {
        foreach ($avisos as $aviso) {
            if (str_contains($aviso, $trecho)) {
                return true;
            }
        }

        return false;
    }
}

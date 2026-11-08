<?php

namespace SqlToEloquent\Geracao\Suporte;

use SqlToEloquent\Dominio\Tabela;

class ConstrutorAuth
{
    public function __construct(
        private readonly NomeadorModel $nomeador = new NomeadorModel,
        private readonly MontadorRegras $montadorRegras = new MontadorRegras,
    ) {
    }

    public function montarController(
        Tabela $tabela,
        string $namespaceControllers,
        string $namespaceModels,
        string $namespaceRequests,
        string $campoEmail,
        string $campoSenha,
        ?string $tabelaUsers = null,
    ): string {
        $modelo = $this->nomeador->classe($tabela->nome, $tabelaUsers);

        return <<<PHP
<?php

namespace {$namespaceControllers};

use {$namespaceModels}\\{$modelo};
use {$namespaceRequests}\\LoginRequest;
use {$namespaceRequests}\\RegisterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest \$request)
    {
        \$user = {$modelo}::query()->create(\$request->validated());
        \$token = \$user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => \$user,
            'token' => \$token,
        ], 201);
    }

    public function login(LoginRequest \$request)
    {
        \$credenciais = [
            '{$campoEmail}' => \$request->validated('{$campoEmail}'),
            '{$campoSenha}' => \$request->validated('{$campoSenha}'),
        ];

        if (! Auth::attempt(\$credenciais)) {
            throw ValidationException::withMessages([
                '{$campoEmail}' => ['Credenciais inválidas.'],
            ]);
        }

        /** @var {$modelo} \$user */
        \$user = Auth::user();
        \$token = \$user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => \$user,
            'token' => \$token,
        ]);
    }

    public function me(Request \$request)
    {
        return \$request->user();
    }

    public function logout(Request \$request)
    {
        \$request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }
}

PHP;
    }

    public function montarLoginRequest(string $namespace, string $campoEmail, string $campoSenha): string
    {
        $regraEmail = str_contains($campoEmail, 'email') ? 'required|email' : 'required|string';

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            '{$campoEmail}' => '{$regraEmail}',
            '{$campoSenha}' => 'required|string',
        ];
    }
}

PHP;
    }

    public function montarRegisterRequest(Tabela $tabela, string $namespace, string $campoEmail, string $campoSenha): string
    {
        $regras = $this->montadorRegras->regras($tabela);
        $permitidos = $this->camposRegistro($tabela, $campoSenha);
        $filtradas = [];

        foreach ($permitidos as $campo) {
            if (! isset($regras[$campo])) {
                continue;
            }

            $filtradas[$campo] = $regras[$campo];
        }

        if (! isset($filtradas[$campoEmail])) {
            $filtradas[$campoEmail] = str_contains($campoEmail, 'email')
                ? ['required', 'email']
                : ['required', 'string'];
        }

        if (! isset($filtradas[$campoSenha])) {
            $filtradas[$campoSenha] = ['required', 'string', 'min:8'];
        } else {
            $filtradas[$campoSenha] = array_values(array_unique([
                ...array_filter(
                    $filtradas[$campoSenha],
                    fn (string $regra) => ! in_array($regra, ['nullable', 'required'], true),
                ),
                'required',
                'string',
                'min:8',
            ]));
        }

        $linhas = [];
        foreach ($filtradas as $campo => $lista) {
            $linhas[] = "            '{$campo}' => '".implode('|', $lista)."',";
        }
        $bloco = "[\n".implode("\n", $linhas)."\n        ]";

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return {$bloco};
    }
}

PHP;
    }

    /**
     * @return list<string>
     */
    private function camposRegistro(Tabela $tabela, string $campoSenha): array
    {
        $excluir = [
            'id',
            'created_at',
            'updated_at',
            'criado_em',
            'atualizado_em',
            'deleted_at',
            'remember_token',
            'email_verified_at',
        ];
        $campos = [];

        foreach ($tabela->colunas as $coluna) {
            if (in_array($coluna->nome, $excluir, true)) {
                continue;
            }

            if ($tabela->chavePrimaria === [$coluna->nome] && $coluna->autoIncremento) {
                continue;
            }

            $campos[] = $coluna->nome;
        }

        if (! in_array($campoSenha, $campos, true)) {
            $campos[] = $campoSenha;
        }

        return $campos;
    }
}

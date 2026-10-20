<?php
/*

Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas

Autor: Bruno Alexandre

Pix (doacao): bruno@brunoalexandre.com

Este projeto e open source. Doe se quiser manter o projeto vivo.

Mais informacoes no README.

*/


namespace Config;

use App\Contracts\JobsProviderInterface;
use App\Libraries\Provedores\ProvedorFalso;
use App\Libraries\Provedores\SerpApi;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * Provedor externo de vagas.
     *
     * Escolhe a implementacao conforme Config\ProvedorVagas::$servico.
     * Todo o sistema consome apenas a interface JobsProviderInterface,
     * portanto trocar de provedor nao exige mudar nenhum outro arquivo.
     *
     * @param bool $getShared Quando true, reaproveita a instancia da requisicao.
     */
    public static function provedorVagas(bool $getShared = true): JobsProviderInterface
    {
        // Instancia compartilhada (o provedor nao guarda estado).
        if ($getShared) {
            return static::getSharedInstance('provedorVagas');
        }

        // Configuracao do provedor (pode ser sobrescrita pelo .env).
        $config = config(ProvedorVagas::class);

        // Provedor falso: apenas para desenvolvimento e testes.
        if (strtolower($config->servico) === 'falso') {
            return new ProvedorFalso($config);
        }

        // Provedor real (padrao).
        return new SerpApi($config);
    }
}

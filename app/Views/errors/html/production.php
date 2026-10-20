<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex">
    <?php /* Pagina de erro em producao do framework, mantida como esta. O
    * projeto usa apenas HTML5, CSS3 puro e JavaScript vanilla: o CSS e o
    * JS inline aqui pertencem ao framework e nao usam nenhuma biblioteca. */ ?>
    <title><?php echo  lang('Errors.whoops') ; ?></title>

    <style>
        <?php echo  preg_replace('#[\r\n\t ]+#', ' ', file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'debug.css')) ; ?>
    </style>
</head>
<body>

    <div class="container text-center">

        <h1 class="headline"><?php echo  lang('Errors.whoops') ; ?></h1>

        <p class="lead"><?php echo  lang('Errors.weHitASnag') ; ?></p>

    </div>

</body>

</html>

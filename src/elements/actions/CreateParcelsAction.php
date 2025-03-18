<?php

namespace craftsnippets\mygls\elements\actions;
use craftsnippets\shippingtoolbox\elements\actions\BaseCreateParcelsAction;
use craftsnippets\mygls\MyGls;
class CreateParcelsAction extends BaseCreateParcelsAction
{
    public static function getPlugin()
    {
        return MyGls::getInstance();
    }
}
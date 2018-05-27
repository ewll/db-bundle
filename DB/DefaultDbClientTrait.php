<?php namespace Ewll\DBBundle\DB;

trait DefaultDbClientTrait
{
    /** @var Client Default DB client */
    protected $defaultDbClient;

    /**
     * @param Client $defaultDbClient
     */
    public function __construct(Client $defaultDbClient)
    {
        $this->defaultDbClient = $defaultDbClient;
    }
}

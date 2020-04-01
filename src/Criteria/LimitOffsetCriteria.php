<?php

namespace ZhangFang\Generator\Criteria;

use Illuminate\Http\Request;
use Prettus\Repository\Contracts\CriteriaInterface;

class LimitOffsetCriteria implements CriteriaInterface
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * 构造方法
     * LimitOffsetCriteria constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * 在查询存储库中应用条件
     * @param $model
     * @param \Prettus\Repository\Contracts\RepositoryInterface $repository
     * @return mixed
     */
    public function apply($model, \Prettus\Repository\Contracts\RepositoryInterface $repository)
    {
        $limit = $this->request->get('limit', null);
        $offset = $this->request->get('offset', null);

        if ($limit) {
            $model = $model->limit($limit);
        }

        if ($offset && $limit) {
            $model = $model->skip($offset);
        }

        return $model;
    }
}

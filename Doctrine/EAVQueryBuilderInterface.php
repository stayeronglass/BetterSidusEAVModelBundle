<?php
/*
 *  Sidus/EAVModelBundle : EAV Data management in Symfony 3
 *  Copyright (C) 2015-2017 Vincent Chalnot
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Sidus\EAVModelBundle\Doctrine;

use Doctrine\ORM\QueryBuilder;
use Sidus\EAVModelBundle\Model\AttributeInterface;

/**
 * Build complex doctrine queries with the EAV model
 *
 * @author Vincent Chalnot <vincent@sidus.fr>
 */
interface EAVQueryBuilderInterface
{
    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder();

    /**
     * @param AttributeInterface $attribute
     *
     * @return AttributeQueryBuilderInterface
     */
    public function attribute(AttributeInterface $attribute);

    /**
     * @param array $eavQueryBuilders
     *
     * @return EAVQueryBuilderInterface
     */
    public function getAnd(array $eavQueryBuilders);

    /**
     * @param array $eavQueryBuilders
     *
     * @return EAVQueryBuilderInterface
     */
    public function getOr(array $eavQueryBuilders);

    /**
     * @return string
     */
    public function getAlias();

    /**
     * @param DQLHandlerInterface $DQLHandler
     *
     * @return QueryBuilder
     */
    public function apply(DQLHandlerInterface $DQLHandler);
}

<?php namespace Ewll\DBBundle\Migration;

interface MigrationInterface
{
    public function getDescription(): string;
    public function up(): string;
    public function down(): string;
}

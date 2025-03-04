<?php
namespace Jsonpost\db\tables;

class HistoryRecord{
    public int $id;
    public int $timestamp;
    public int $id_account;
    public int $pow_score;
    public int $pow_required;
}

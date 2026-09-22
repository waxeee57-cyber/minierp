<?php

namespace Modules\Crm\Contracts;

use Illuminate\Support\Collection;

/** Olvasási felület az ügyfélkörhöz más modulok (pl. AI-asszisztens) számára. */
interface CustomerDirectory
{
    /** @return Collection<int, array{id:int, name:string, company:?string, city:?string}> */
    public function search(string $term, int $limit = 5): Collection;

    /** Az ügyfél teljes helyzetképe személyes elérhetőségek nélkül. */
    public function profile(int $customerId): ?array;

    /** @param  list<int>  $ids  @return array<int, string> azonosító → megjelenítendő név */
    public function namesFor(array $ids): array;

    /** @return Collection<int, array{customer:string, subject:string, due_at:?string, overdue:bool}> */
    public function openTasks(int $limit = 20): Collection;
}

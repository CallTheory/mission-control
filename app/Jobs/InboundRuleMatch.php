<?php

namespace App\Jobs;

use App\Models\InboundEmail;
use App\Models\InboundEmailRules;
use App\Models\MergeCommISWebTrigger;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InboundRuleMatch implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public InboundEmail $email;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(InboundEmail $email)
    {
        $this->email = $email;
    }

    public function uniqueId(): string
    {
        return $this->email->id;
    }

    public function handle(): void
    {
        $matches = $this->findMatchingRule();

        if (count($matches) > 0) {
            foreach ($matches as $matched_rule) {
                if (! $matched_rule->enabled) {
                    $this->email->ignored_at = Carbon::now();
                    $this->email->save();
                } else {
                    if (Str::startsWith($matched_rule->category ?? 'none', ['database:merge:', 'database:replace:'])) {
                        DatabaseAttachmentSaveJob::dispatch($this->email, $matched_rule->category);
                    } else {
                        if ($matched_rule->account) {
                            SendEmailToAmtelcoSMTP::dispatch($this->email, $matched_rule->category ?? 'none');
                        } else {
                            $isweb = MergeCommISWebTrigger::findOrFail($matched_rule->mergecomm_trigger_id);
                            SendEmailToMergeComm::dispatch($this->email, $matched_rule->category ?? 'none', $isweb);
                        }
                    }

                }
            }
        } else {
            $this->email->ignored_at = Carbon::now();
            $this->email->save();
        }
    }

    public function findMatchingRule(): array
    {
        $matches = [];

        $rules = InboundEmailRules::where('enabled', true)->get();

        foreach ($rules as $rule) {

            $settings = json_decode($rule->rules, true);
            $allMatch = true;
            $toOrFromMatched = false;

            foreach ($settings as $field => $modifiers) {
                foreach ($modifiers as $modifier => $items) {
                    foreach ($items as $val) {

                        $match = false;

                        if ($field === 'to') {
                            $toOrFromMatched = true;
                            if ($modifier === 'contains') {
                                if (Str::contains(strtolower($this->email->to), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'starts_with') {
                                if (Str::startsWith(strtolower($this->email->to), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'ends_with') {
                                if (Str::endsWith(strtolower($this->email->to), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'exact_match') {
                                if (strtolower($this->email->to) === strtolower($val)) {
                                    $match = true;
                                }
                            }
                        } elseif ($field === 'from') {
                            $toOrFromMatched = true;
                            if ($modifier === 'contains') {
                                if (Str::contains(strtolower($this->email->from), strtolower($val))) {
                                    $match = true;
                                }

                                $parts = explode('<', $this->email->from);
                                if (Str::contains(strtolower(Str::replace(['>', '<'], '', $parts[1] ?? '')), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'starts_with') {
                                if (Str::startsWith(strtolower($this->email->from), strtolower($val))) {
                                    $match = true;
                                }

                                $parts = explode('<', $this->email->from);
                                if (Str::startsWith(strtolower(Str::replace(['>', '<'], '', $parts[1] ?? '')), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'ends_with') {
                                if (Str::endsWith(strtolower($this->email->from), strtolower($val))) {
                                    $match = true;
                                }

                                $parts = explode('<', $this->email->from);
                                if (Str::endsWith(strtolower(Str::replace(['>', '<'], '', $parts[1] ?? '')), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'exact_match') {
                                if (strtolower($this->email->from) === strtolower($val)) {
                                    $match = true;
                                }

                                $parts = explode('<', $this->email->from);
                                if (strtolower(Str::replace(['>', '<'], '', $parts[1] ?? '')) === strtolower($val)) {
                                    $match = true;
                                }
                            }
                        } elseif ($field === 'subject') {
                            if ($modifier === 'contains') {
                                if (Str::contains(strtolower($this->email->subject), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'starts_with') {
                                if (Str::startsWith(strtolower($this->email->subject), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'ends_with') {
                                if (Str::endsWith(strtolower($this->email->subject), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'exact_match') {
                                if (strtolower($this->email->subject) === strtolower($val)) {
                                    $match = true;
                                }
                            }
                        } elseif ($field === 'text') {
                            if ($modifier === 'contains') {
                                if (Str::contains(strtolower($this->email->text), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'starts_with') {
                                if (Str::startsWith(strtolower($this->email->text), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'ends_with') {
                                if (Str::endsWith(strtolower($this->email->text), strtolower($val))) {
                                    $match = true;
                                }
                            } elseif ($modifier === 'exact_match') {
                                if (strtolower($this->email->text) === strtolower($val)) {
                                    $match = true;
                                }
                            }
                        } elseif ($field === 'attachment' && $this->email->attachment_info ?? false) {
                            if ($modifier === 'contains') {
                                foreach (json_decode($this->email->attachment_info, true) as $attachment) {
                                    if (Str::contains(strtolower($attachment['filename']), strtolower($val))) {
                                        $match = true;
                                    }
                                }
                            } elseif ($modifier === 'starts_with') {
                                foreach (json_decode($this->email->attachment_info, true) as $attachment) {
                                    if (Str::startsWith(strtolower($attachment['filename']), strtolower($val))) {
                                        $match = true;
                                    }
                                }
                            } elseif ($modifier === 'ends_with') {
                                foreach (json_decode($this->email->attachment_info, true) as $attachment) {
                                    if (Str::endsWith(strtolower($attachment['filename']), strtolower($val))) {
                                        $match = true;
                                    }
                                }
                            } elseif ($modifier === 'exact_match') {
                                foreach (json_decode($this->email->attachment_info, true) as $attachment) {
                                    if (strtolower($attachment['filename']) === strtolower($val)) {
                                        $match = true;
                                    }
                                }
                            }
                        }

                        if (! $match) {
                            Log::info('Inbound Rule Match Fail', ['rule' => $rule->id, 'field' => $field, 'modifier' => $modifier, 'val' => $val]);
                            $allMatch = false;
                            break 3; // Exit all loops if any rule does not match
                        }

                    }
                }
            }

            if ($allMatch && $toOrFromMatched) {
                $matches[$rule->id] = $rule;
            }
        }

        Log::info('Inbound Rule Matches', ['matches' => $matches]);

        return $matches;
    }
}

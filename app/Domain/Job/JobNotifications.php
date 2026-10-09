<?php declare(strict_types=1);
namespace App\Domain\Job;
use App\Domain\Review\Review;
use App\Model\Mail\MailSender;
use Nette\Application\LinkGenerator;
final class JobNotifications
{
    public function __construct(private MailSender $mail, private LinkGenerator $links) {}
    public function agreement(Job $job): void
    { $this->send($job->getProvider()->getEmail(), 'Potvrďte dohodu so zákazníkom', 'Zákazník vás vybral na realizáciu. Potvrďte alebo odmietnite dohodu v prehľade realizácií.', $this->links->link('Front:ProviderDemand:default')); }
    public function reviewInvitation(Job $job): void
    {
        $demand = $job->getDemand();
        $link = $demand->getCustomer() === null ? $this->links->link('Front:Demand:recover') : $this->links->link('Front:Demand:detail', ['reference' => $demand->getReference()]);
        $this->send($demand->getContactEmail(), 'Podeľte sa o skúsenosť s poskytovateľom', 'Vaše oznámenie vykonaných prác je uložené. Môžete ohodnotiť svoju skúsenosť. Pri vypršanom prístupe si obnovte odkaz. Číslo dopytu: ' . $demand->getReference(), $link);
    }
    public function newReview(Review $review): void
    { $this->send($review->getJob()->getProvider()->getEmail(), 'Nová recenzia vašej práce', 'Zákazník odoslal recenziu vašej práce. Stav recenzie a možnosť odpovede nájdete v prehľade realizácií.', $this->links->link('Front:ProviderDemand:default')); }
    private function send(string $email, string $subject, string $message, string $link): void
    {
        try { $this->mail->sendCustomerLink($email, $subject, $message, $link); }
        catch (\Throwable $e) { \Tracy\Debugger::log('Job notification delivery failed.', 'error'); }
    }
}

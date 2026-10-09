<?php declare(strict_types=1);
// Runs in CustomerDemandTest's transient SQLite database and captured mail transport.
use App\Domain\Job\{Job, JobFacade, JobNotifications};
use App\Domain\Review\{Review, ReviewFacade};
use Tester\Assert;
$jobRepository = new \App\Infrastructure\Persistence\Doctrine\DoctrineJobRepository($em);
$reviewRepository = new \App\Infrastructure\Persistence\Doctrine\DoctrineReviewRepository($em);
$notifications = new JobNotifications(new \App\Model\Mail\MailSender($factory), $links);
$jobs = new JobFacade($em, $demands, $demandRepository, $jobRepository, $invitationRepository, $invitations, $providerRepository, $reviewRepository, $notifications);
$reviews = new ReviewFacade($em, $jobs, $jobRepository, $reviewRepository, $invitations, $providerRepository, $demandRepository, $demands, $audit, $notifications);
$reviewProvider = $makeProvider('ReviewCompany'); $replacement = $makeProvider('ReviewReplacement');
$createReviewDemand = function () use ($em, $city, $category, $reviewProvider, $replacement): array {
    $d = new \App\Domain\Demand\Demand(bin2hex(random_bytes(16)), bin2hex(random_bytes(32)), 'Clean the apartment.', $city, $category, 'By agreement', 'Guest', 'review-guest@example.test', null, null);
    $raw = $d->issueAccess(); $d->verify(); $em->persist($d);
    $i = new \App\Domain\Matching\ProviderInvitation($d, $reviewProvider, 'Invited'); $i->answer(true); $em->persist($i);
    $r = new \App\Domain\Matching\ProviderInvitation($d, $replacement, 'Invited'); $r->answer(true); $em->persist($r); $em->flush();
    return [$d, $i, $r, hash('sha256', $raw)];
};
[$d, $i, $r, $hash] = $createReviewDemand();
Assert::exception(fn() => $jobs->select($d, $i->getId(), null, 'wrong'), InvalidArgumentException::class);
$beforeMail = count($sender->messages);
$job = $jobs->select($d, $i->getId(), null, $hash);
Assert::same($job, $jobs->select($d, $i->getId(), null, $hash));
Assert::same($beforeMail + 1, count($sender->messages));
Assert::exception(fn() => $jobs->select($d, $r->getId(), null, $hash), InvalidArgumentException::class);
Assert::exception(fn() => $matching->requeue($d, $category->getId()), InvalidArgumentException::class);
Assert::true($d->isMatchingStopped());
Assert::false($reviews->canReview($job, null, $hash));
Assert::exception(fn() => $jobs->providerAction($job->getId(), $replacement, 'confirm'), InvalidArgumentException::class);
Assert::exception(fn() => $jobs->report($job->getId(), null, 'wrong', 'completed', date('Y-m-d'), 'Cleaning'), InvalidArgumentException::class);
Assert::exception(fn() => $jobs->report($job->getId(), null, $hash, 'completed', '2026-02-30', 'Cleaning'), InvalidArgumentException::class);
Assert::exception(fn() => $jobs->report($job->getId(), null, $hash, 'completed', date('Y-m-d', strtotime('+1 day')), 'Cleaning'), InvalidArgumentException::class);
$jobs->providerAction($job->getId(), $reviewProvider, 'confirm');
$jobs->report($job->getId(), null, $hash, 'partial', date('Y-m-d'), 'Only one room cleaned.');
Assert::true($reviews->canReview($job, null, $hash));
Assert::exception(fn() => $reviews->submit($job->getId(), null, 'wrong', 1, 'Bad work'), InvalidArgumentException::class);
Assert::exception(fn() => $reviews->submit($job->getId(), null, $hash, 0, 'Invalid'), InvalidArgumentException::class);
Assert::exception(fn() => $reviews->submit($job->getId(), null, $hash, 6, 'Invalid'), InvalidArgumentException::class);
$negative = $reviews->submit($job->getId(), null, $hash, 1, '<script>alert(1)</script> Poor quality.');
Assert::same(Review::PUBLISHED, $negative->getStatus());
Assert::same($negative, $reviews->submit($job->getId(), null, $hash, 5, 'Replay'));
Assert::same(['count' => 1, 'average' => 1.0], $reviews->summary($reviewProvider));
Assert::exception(fn() => $reviews->reply($negative->getId(), $replacement, 'Other company'), InvalidArgumentException::class);
$reviews->reply($negative->getId(), $reviewProvider, 'We disagree with the quality assessment.');
Assert::same(Review::PUBLISHED, $negative->getStatus());
Assert::exception(fn() => $reviews->reply($negative->getId(), $reviewProvider, 'Second reply'), InvalidArgumentException::class);
// Database uniqueness protects even writes that bypass the facade.
foreach (['job' => ['active_demand_id' => $d->getId()], 'review' => ['job_id' => $job->getId()]] as $table => $criteria) {
    $row = $em->getConnection()->fetchAssociative('SELECT * FROM ' . $table . ' WHERE id = ?', [$table === 'job' ? $job->getId() : $negative->getId()]); unset($row['id']);
    Assert::exception(fn() => $em->getConnection()->insert($table, $row), \Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
}
// Collaboration denial pauses publication and requires an administrator; quality disagreement above did not.
$jobs->providerAction($job->getId(), $reviewProvider, 'dispute', 'We did not work on this property.');
Assert::same(Review::PENDING, $negative->getStatus()); Assert::same(0, $reviews->summary($reviewProvider)['count']);
Assert::exception(fn() => $reviews->decide($negative->getId(), true, 'Checked'), \Nette\Application\ForbiddenRequestException::class);
$security->login($admin->toIdentity());
$reviews->decide($negative->getId(), true, 'Support verified the agreement.');
Assert::same(Job::ADMIN_VERIFIED, $job->getVerification()); Assert::same($admin->getId(), $negative->getDecidedBy()->getId());
Assert::same(1, $reviews->summary($reviewProvider)['count']);
$security->logout(true);
// Unconfirmed work requires review, even after demand closure. Closure alone never allows a review.
[$pendingDemand, $pendingInvitation, $unused, $pendingHash] = $createReviewDemand();
$pendingJob = $jobs->select($pendingDemand, $pendingInvitation->getId(), null, $pendingHash);
$pendingDemand->close(); $em->flush(); Assert::false($reviews->canReview($pendingJob, null, $pendingHash));
$jobs->report($pendingJob->getId(), null, $pendingHash, 'completed', date('Y-m-d'), 'Cleaning');
$pending = $reviews->submit($pendingJob->getId(), null, $pendingHash, 5, 'Great');
Assert::same(Review::PENDING, $pending->getStatus()); Assert::same(1, $reviews->summary($reviewProvider)['count']);
// Cancellation retains history and permits a replacement without restarting matching.
[$cancelDemand, $cancelInvitation, $replaceInvitation, $cancelHash] = $createReviewDemand();
$cancelJob = $jobs->select($cancelDemand, $cancelInvitation->getId(), null, $cancelHash);
$jobs->report($cancelJob->getId(), null, $cancelHash, 'ongoing', '', ''); Assert::false($cancelJob->isReviewable());
$jobs->report($cancelJob->getId(), null, $cancelHash, 'not_started', '', ''); Assert::null($jobs->current($cancelDemand));
$replaceJob = $jobs->select($cancelDemand, $replaceInvitation->getId(), null, $cancelHash);
Assert::count(2, $jobs->forDemand($cancelDemand)); Assert::false($cancelJob->isReviewable());
Assert::true($cancelDemand->isMatchingStopped());
// Expired guest access rotates through the existing recovery workflow, including for long jobs.
(new ReflectionProperty(\App\Domain\Demand\Demand::class, 'accessExpiresAt'))->setValue($pendingDemand, new DateTimeImmutable('-1 second')); $em->flush();
Assert::false($reviews->canReview($pendingJob, null, $pendingHash));
$demands->requestAccess($pendingDemand->getReference(), $pendingDemand->getContactEmail()); $newToken = $sender->token();
Assert::same($pendingDemand, $demands->findByToken($newToken)); Assert::false($demands->canAccess($pendingDemand, null, $pendingHash));
// Actual pages render forms, escaped customer text, and server authorization.
$security->logout(true);
[$response, $html] = $run('Front:ProviderProfile', ['action' => 'detail', 'id' => $reviewProvider->getId()]);
Assert::contains('&lt;script&gt;', $html); Assert::notContains('<script>alert(1)</script>', $html); Assert::contains('Práce vykonané čiastočne', $html);
$security->login($reviewProvider->toIdentity());
[$response, $html] = $run('Front:ProviderDemand', ['action' => 'default']); Assert::contains('Vaše realizácie', $html);
$security->login($admin->toIdentity());
[$response, $html] = $run('Admin:Review', ['action' => 'default']); Assert::contains('Recenzie na preverenie', $html);
[$response, $html, $p] = $run('Admin:Review', ['action' => 'detail', 'id' => $pending->getId()]);
$run('Admin:Review', ['action' => 'detail', 'id' => $pending->getId(), 'do' => 'decisionForm-submit'], 'POST', ['publish' => '1', 'reason' => 'Verified', 'submit' => 'Uložiť']);
Assert::same(Review::PENDING, $pending->getStatus());
$reviews->decide($pending->getId(), false, 'Collaboration could not be verified.'); Assert::same(Review::REJECTED, $pending->getStatus());
Assert::exception(fn() => $jobs->providerAction($pendingJob->getId(), $reviewProvider, 'dispute', 'Repeated dispute'), InvalidArgumentException::class);
Assert::same(Job::UNVERIFIED, $pendingJob->getVerification());
$security->logout(true);
// End-to-end customer writes require a scoped guest grant and valid CSRF for each form.
[$uiDemand, $uiInvitation, $unused, $uiHash] = $createReviewDemand();
$session->getSection('demandAccess')[$uiDemand->getReference()] = $uiHash;
[$response, $html, $p] = $run('Front:Demand', ['action' => 'detail', 'reference' => $uiDemand->getReference()]);
$component = 'selectProviderForm-' . $uiInvitation->getId();
$post = ['submit' => 'Dohodli sme sa'];
$run('Front:Demand', ['action' => 'detail', 'reference' => $uiDemand->getReference(), 'do' => $component . '-submit'], 'POST', $post);
Assert::null($jobs->current($uiDemand));
$post[\Nette\Forms\Form::ProtectorId] = $p[$component][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value');
[$response] = $run('Front:Demand', ['action' => 'detail', 'reference' => $uiDemand->getReference(), 'do' => $component . '-submit'], 'POST', $post);
Assert::type(\Nette\Application\Responses\RedirectResponse::class, $response);
$uiJob = $jobs->current($uiDemand); Assert::type(Job::class, $uiJob);
$jobs->providerAction($uiJob->getId(), $reviewProvider, 'confirm');
[$response, $html, $p] = $run('Front:Demand', ['action' => 'detail', 'reference' => $uiDemand->getReference()]);
$post = ['outcome' => 'completed', 'date' => date('Y-m-d'), 'description' => 'Cleaning', 'submit' => 'Uložiť'];
$run('Front:Demand', ['action' => 'detail', 'reference' => $uiDemand->getReference(), 'do' => 'workForm-submit'], 'POST', $post);
Assert::false($uiJob->isReviewable());
$post[\Nette\Forms\Form::ProtectorId] = $p['workForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value');
$run('Front:Demand', ['action' => 'detail', 'reference' => $uiDemand->getReference(), 'do' => 'workForm-submit'], 'POST', $post);
Assert::true($uiJob->isReviewable());
[$response, $html, $p] = $run('Front:Demand', ['action' => 'detail', 'reference' => $uiDemand->getReference()]);
$post = ['rating' => '1', 'text' => 'Poor quality', 'submit' => 'Odoslať'];
$run('Front:Demand', ['action' => 'detail', 'reference' => $uiDemand->getReference(), 'do' => 'reviewForm-submit'], 'POST', $post);
Assert::null($reviews->forJob($uiJob));
$post[\Nette\Forms\Form::ProtectorId] = $p['reviewForm'][\Nette\Forms\Form::ProtectorId]->getControl()->getAttribute('value');
$run('Front:Demand', ['action' => 'detail', 'reference' => $uiDemand->getReference(), 'do' => 'reviewForm-submit'], 'POST', $post);
Assert::same(Review::PUBLISHED, $reviews->forJob($uiJob)->getStatus());
// A dispute without a review records its administrative decision in the audit.
[$disputedDemand, $disputedInvitation, $unused, $disputedHash] = $createReviewDemand();
$disputedJob = $jobs->select($disputedDemand, $disputedInvitation->getId(), null, $disputedHash);
$jobs->providerAction($disputedJob->getId(), $reviewProvider, 'dispute', 'Please verify which property this refers to.');
$security->login($admin->toIdentity());
[$response, $html] = $run('Admin:Review', ['action' => 'default']); Assert::contains('Please verify which property', $html);
$reviews->resolveJob($disputedJob->getId(), true, 'Support checked the agreement.');
Assert::same(Job::ADMIN_VERIFIED, $disputedJob->getVerification());
$history = $audit->history('job', $disputedJob->getId()); Assert::count(1, $history); Assert::same($admin->getId(), $history[0]->getActor()->getId());
$security->logout(true);
for ($attempt = 0; $attempt < 50 && $invitations->deliverOne(); $attempt++) {}
$em->refresh($uiInvitation); Assert::same('cancelled', $uiInvitation->getDelivery());
echo "Job selection, review permissions, publication, disputes, constraints, recovery and UI passed.\n";

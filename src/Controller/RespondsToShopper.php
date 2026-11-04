<?php

namespace Summit\Wishlist\Controller;

use Tools;

/**
 * Shared response handling for the module's POST controllers: JSON for the
 * JavaScript enhancement, otherwise a redirect back with flash notifications.
 * Used only inside ModuleFrontController subclasses.
 */
trait RespondsToShopper
{
    protected function isPostRequest(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    protected function wantsJson(): bool
    {
        return (bool) Tools::getValue('ajax')
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    /**
     * @param string[] $messages
     */
    protected function respond(bool $success, array $messages, array $data = []): void
    {
        if ($this->wantsJson()) {
            header('Content-Type: application/json');
            $this->ajaxRender(json_encode(['success' => $success, 'messages' => $messages] + $data));
            exit;
        }

        foreach ($messages as $message) {
            if ($success) {
                $this->success[] = $message;
            } else {
                $this->errors[] = $message;
            }
        }
        $this->redirectWithNotifications($this->backUrl());
    }

    private function backUrl(): string
    {
        $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referrer !== '' && Tools::urlBelongsToShop($referrer)) {
            return $referrer;
        }

        return $this->module->getWishlistUrl();
    }
}

        case 'checktok':
          return $wpwp->user_checktok();
    /wp/post/get/:id
    /wp/post/update/:id
    /wp/post/delete/:id

    /wp/post/list/
    /wp/post/list/:id

        case 'get':
          return $wpwp->post_get($para2);
        case 'update':
          return $wpwp->post_update($para2);
        case 'delete':
          return $wpwp->post_delete($para2);

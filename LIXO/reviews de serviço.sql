SELECT
    r.review_id,
    r.overall_rating,
    r.comment,
    r.created_at,
    u.first_name,
    u.last_name,
    u.profile_image_url,
    c.contract_id,
    c.title AS contract_title
FROM
    Reviews r
JOIN
    Contracts c ON r.contract_id = c.contract_id
JOIN
    Users u ON r.reviewer_id = u.user_id
WHERE
    c.service_id = :service_id
    AND r.reviewee_id = (SELECT user_id FROM FreelancerProfiles WHERE profile_id = :freelancer_id)
ORDER BY
    r.created_at DESC;
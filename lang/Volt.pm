package Locale::Maketext::Extract::Plugin::Volt;
$Locale::Maketext::Extract::Plugin::Volt::VERSION = '1.00';
use strict;
use base qw(Locale::Maketext::Extract::Plugin::Base);

# ABSTRACT: Volt template parser


sub file_types {
    return qw( volt );
}

sub extract {
    my $self = shift;
    local $_ = shift;

    my $line = 1;

    # Volt Template:
    $line = 1;
    pos($_) = 0;
    while (m/\G(.*?(?<!\{)\{\{(?!\{)\s*lang\._\('(.*?)'\)\s*\}\})/sg) {
        my ( $vars, $str ) = ( '', $2 );
        $line += ( () = ( $1 =~ /\n/g ) );    # cryptocontext!
        $self->add_entry( $str, $line, $vars );
    }
}


1;

__END__
